<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Question;
use App\Models\Test;
use App\Models\Section;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Services\SatScoringService;
use App\Http\Requests\SubmitModuleRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TestTakingController extends Controller
{
    protected $scoringService;

    public function __construct(SatScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
    }

    /**
     * Get attempt status/options for a test
     */
    public function attemptOptions($testId)
    {
        $user = Auth::user();
        $test = Test::where('id', $testId)->where('status', 'active')->firstOrFail();

        $latestInProgress = UserTest::where('user_id', $user->id)
            ->where('test_id', $test->id)
            ->where('status', 'in_progress')
            ->latest('updated_at')
            ->first();

        $latestCompleted = UserTest::where('user_id', $user->id)
            ->where('test_id', $test->id)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();

        $firstSection = $test->sections()->orderBy('order')->first();
        $firstModule = $firstSection ? $firstSection->modules()->orderBy('order')->first() : null;
        $firstModuleUlid = $firstModule ? $firstModule->ulid : null;

        return response()->json([
            'has_in_progress' => !empty($latestInProgress),
            'latest_in_progress_ulid' => $latestInProgress?->ulid,
            'latest_in_progress_current_module_ulid' => $latestInProgress?->currentModule?->ulid ?? $firstModuleUlid,
            'first_module_ulid' => $firstModuleUlid,
            'can_continue' => !empty($latestInProgress),
            'can_start_fresh' => true,
        ]);
    }

    /**
     * Initialize or resume a test
     */
    public function startTest(Request $request, $testId)
    {
        $user = Auth::user();
        $test = Test::where('id', $testId)->where('status', 'active')->firstOrFail();

        $mode = $request->input('mode', 'fresh');

        if ($mode === 'fresh') {
            UserTest::where('user_id', $user->id)
                ->where('test_id', $test->id)
                ->where('status', 'in_progress')
                ->delete();

            $userTest = UserTest::create([
                'user_id' => $user->id,
                'test_id' => $test->id,
                'status' => 'in_progress',
            ]);
        } else {
            $userTest = UserTest::where('user_id', $user->id)
                ->where('test_id', $test->id)
                ->where('status', 'in_progress')
                ->latest('updated_at')
                ->first();

            if (!$userTest) {
                $userTest = UserTest::create([
                    'user_id' => $user->id,
                    'test_id' => $test->id,
                    'status' => 'in_progress',
                ]);
            }
        }

        $firstSection = $test->sections()->orderBy('order')->first();
        $firstModule = $firstSection ? $firstSection->modules()->orderBy('order')->first() : null;
        $firstModuleUlid = $firstModule ? $firstModule->ulid : null;

        return response()->json([
            'user_test_id' => $userTest->id,
            'user_test_ulid' => $userTest->ulid,
            'first_module_ulid' => $firstModuleUlid,
            'redirect_url' => route('take-test', ['ulid' => $firstModuleUlid]) . '?attempt=' . $userTest->ulid,
            'message' => 'Test started',
        ]);
    }

    public function showModule($ulid = null)
    {
        if ($ulid === null || $ulid === 'preview-rw') {
            return $this->showStaticPreview('reading_writing');
        }
        if ($ulid === 'preview-math') {
            return $this->showStaticPreview('math');
        }

        $user = Auth::user();
        $module = null;
        if ($ulid) {
            $module = Module::visibleTo($user)
                ->where('ulid', $ulid)
                ->firstOrFail();

            [$section, $test] = $this->resolveModuleContext($module, $user);
            $this->loadCurrentModuleQuestions($module);
        } else {
            $test = Test::visibleTo($user)
                ->with([
                    'sections' => fn($q) => $q->orderBy('order'),
                ])->whereIn('status', ['active', 'draft'])
                ->orderByRaw("CASE WHEN title = 'Test Preview' THEN 0 ELSE 1 END")
                ->first();

            if (! $test) {
                abort(404, 'No test available. Please create a test first.');
            }

            if ($test->sections->isEmpty()) {
                abort(404, 'Test has no sections. Please add sections and modules first.');
            }

            // Default to first module of first section
            $section = $test->sections->firstWhere('type', 'reading_writing') ?? $test->sections->first();
            $module = $this->firstModuleForSection($section, $user);

            if ($module) {
                $this->loadCurrentModuleQuestions($module);
            }
        }

        if (! $module) {
            abort(404, 'No module found. Please add modules first.');
        }

        // Get questions ordered by position (defined in Module::questions relationship)
        $questions = $module->questions;
        if ($questions->isEmpty()) {
            abort(404, 'Module has no questions.');
        }

        // Security: Hide 'is_correct' attribute to prevent leaking answers to students.
        $questions->each(function($question) {
            $question->answerChoices->makeHidden('is_correct');
        });

        $currentQuestion = 1;
        $totalQuestions = $questions->count();

        $isPreview = ($test->title === 'Test Preview');
        $durationMinutes = $isPreview ? 0 : ($module->duration_minutes ?? ($section->type === 'math' ? 35 : 32));

        // Determine next module for navigation (simple logic for now)
        [$nextModule, $nextModuleSection] = $this->resolveNextModule($module, $section, $test, $user);

        // Determine which view to use based on section type
        $viewName = $section->type === 'math' ? 'tests.take.math' : 'tests.take.reading';

        // Get user test record
        $userTest = null;
        $savedAnswers = collect();
        if (Auth::check()) {
            $attemptUlid = request()->query('attempt');

            if ($attemptUlid) {
                $userTest = \App\Models\UserTest::where('ulid', $attemptUlid)->first();
                if (!$userTest) {
                    abort(404, 'Attempt not found.');
                }
                if ((int) $userTest->user_id !== (int) Auth::id()) {
                    abort(403, 'Unauthorized.');
                }
                if ((int) $userTest->test_id !== (int) $test->id) {
                    abort(400, 'Attempt does not belong to this test.');
                }
            } else {
                // Legacy fallback: find latest in_progress attempt
                $userTest = \App\Models\UserTest::where('user_id', Auth::id())
                    ->where('test_id', $test->id)
                    ->where('status', 'in_progress')
                    ->latest('updated_at')
                    ->first();

                if (!$userTest) {
                    // Create new attempt
                    $userTest = \App\Models\UserTest::create([
                        'user_id' => Auth::id(),
                        'test_id' => $test->id,
                        'status' => 'in_progress',
                    ]);
                }
            }

            if ((int) $userTest->current_module_id !== (int) $module->id) {
                $userTest->current_module_id = $module->id;
                $userTest->current_module_started_at = now();
                $userTest->current_module_elapsed_seconds = 0;
                $userTest->save();
            } else if ($userTest->current_module_started_at && !$isPreview) {
                // Practice resumption: reset started_at to now() to restart session timer
                $userTest->current_module_started_at = now();
                $userTest->save();

                // Compute remaining duration based on saved accumulated elapsed seconds
                $elapsedSeconds = $userTest->current_module_elapsed_seconds;
                $totalSeconds = $durationMinutes * 60;
                $remainingSeconds = max(0, $totalSeconds - $elapsedSeconds);
                $durationMinutes = $remainingSeconds / 60;
            }

            $savedAnswers = UserTestAnswer::where('user_test_id', $userTest->id)
                ->whereIn('question_id', $questions->pluck('id'))
                ->pluck('selected_answer', 'question_id');
        }

        $testData = (object) [
            'id' => $test->id,
            'page_title' => "Section {$section->order}, Module {$module->module_number}: {$section->name}",
            'section_title' => "{$section->name} - Module {$module->module_number}",
            'section_number' => $section->order,
            'module_number' => $module->module_number,
            'module_id' => $module->id,
            'username' => \Illuminate\Support\Facades\Auth::user()?->username ?? 'Guest',
            'is_preview' => $isPreview,
            'duration_minutes' => $durationMinutes,
        ];

        return view($viewName, [
            'testData' => $testData,
            'questions' => $questions,
            'currentQuestion' => $currentQuestion,
            'totalQuestions' => $totalQuestions,
            'sectionNumber' => $section->order,
            'moduleNumber' => $module->module_number,
            'sectionName' => $section->name,
            'sectionType' => $section->type,
            'nextModuleId' => $nextModule ? $nextModule->ulid : null,
            'nextModuleName' => $nextModule ? ($nextModule->module_number == 2 ? 'Module 2' : 'Section ' . ($nextModuleSection?->order ?? '')) : null,
            'userTestId' => $userTest ? $userTest->id : null,
            'userTestUlid' => $userTest ? $userTest->ulid : null,
            'userTest' => $userTest,
            'savedAnswers' => $savedAnswers,
        ]);
    }

    private function resolveModuleContext(Module $module, $user): array
    {
        $activeVisibleTest = fn($query) => $query
            ->visibleTo($user)
            ->whereIn('status', ['active', 'draft']);

        $sectionQuery = $module->sections()
            ->with('test')
            ->whereHas('test', $activeVisibleTest);

        if ($module->section_id) {
            $sectionQuery->orderByRaw('CASE WHEN sections.id = ? THEN 0 ELSE 1 END', [$module->section_id]);
        }

        $section = $sectionQuery
            ->orderBy('sections.order')
            ->first();

        if (! $section && $module->section_id) {
            $section = Section::with('test')
                ->whereKey($module->section_id)
                ->whereHas('test', $activeVisibleTest)
                ->first();
        }

        if (! $section || ! $section->test) {
            abort(404, 'Module is not attached to an available test.');
        }

        return [$section, $section->test];
    }

    private function loadCurrentModuleQuestions(Module $module): void
    {
        $module->load([
            'questions.passage',
            'questions.answerChoices' => fn($q) => $q->orderBy('order'),
        ]);
    }

    private function firstModuleForSection(Section $section, $user): ?Module
    {
        return $section->modules()
            ->visibleTo($user)
            ->first();
    }

    private function resolveNextModule(Module $module, Section $section, Test $test, $user): array
    {
        if ((int) $module->module_number === 1) {
            $nextModule = $section->modules()
                ->visibleTo($user)
                ->where('module_number', 2)
                ->reorder()
                ->orderByRaw('CASE WHEN difficulty_level = ? THEN 0 ELSE 1 END', [Module::DIFFICULTY_HARD])
                ->orderBy('modules.order')
                ->first();

            if ($nextModule) {
                return [$nextModule, $section];
            }
        }

        $nextSection = $test->sections()
            ->where('order', '>', $section->order)
            ->orderBy('order')
            ->first();

        if (! $nextSection) {
            return [null, null];
        }

        $nextModule = $nextSection->modules()
            ->visibleTo($user)
            ->where('module_number', 1)
            ->reorder()
            ->orderBy('modules.order')
            ->first();

        return [$nextModule, $nextModule ? $nextSection : null];
    }

    /**
     * Submit answers for a module and get next routing
     */
    public function submitModule(SubmitModuleRequest $request)
    {
        try {
            \Illuminate\Support\Facades\Log::info("submitModule called", [
                'user_test_id' => $request->input('user_test_id'),
                'module_id' => $request->input('module_id')
            ]);

            $validated = $request->validated();

            return DB::transaction(function () use ($validated) {
                [$userTest, $module] = $this->resolveSubmissionContext($validated);
                $section = $module->section;
                
                if ($userTest->current_module_started_at) {
                    $test = $module->section->test;
                    $duration = ($test && $test->title === 'Test Preview') ? 0 : ($module->duration_minutes ?? ($section->type === 'math' ? 35 : 32));
                    if ($duration > 0) {
                        $maxAllowedTime = $userTest->current_module_started_at->copy()->addMinutes($duration + 5);
                        
                        if (now()->greaterThan($maxAllowedTime)) {
                            throw new AuthorizationException('Module submission time has expired.');
                        }
                    }
                }

                // 1. Save answers
                $this->saveModuleAnswers($userTest, $module, $validated['answers']);

                // 2. Logic for Routing or Finalizing - Run synchronously
                \App\Jobs\ScoreModuleJob::dispatchSync($userTest->id, $module->id, $section->id);

                // 3. Return the synchronous result from cache immediately
                $cacheKey = "scoring_result_{$userTest->id}";
                if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                    $result = \Illuminate\Support\Facades\Cache::get($cacheKey);
                    \Illuminate\Support\Facades\Cache::forget($cacheKey); // Clean up
                    return response()->json($result);
                }

                return response()->json([
                    'error' => 'Scoring failed',
                    'message' => 'Unable to determine test routing.'
                ], 500);
            });

        } catch (AuthorizationException $e) {
            return response()->json([
                'error' => 'Unauthorized submission.',
                'message' => $e->getMessage(),
            ], 403);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("EXCEPTION in submitModule", ['exception' => $e]);
            return response()->json([
                'error' => 'Server error during submission.',
                'message' => 'An unexpected server error occurred.'
            ], 500);
        }
    }

    public function autosaveModule(Request $request)
    {
        $validated = $request->validate([
            'user_test_id' => 'required|exists:user_tests,id',
            'module_id' => 'required|exists:modules,id',
            'answers' => 'present|array|max:100',
            'answers.*' => 'nullable|string|max:100',
            'elapsed_seconds' => 'nullable|integer|min:0',
        ]);

        try {
            $savedCount = DB::transaction(function () use ($validated, $request) {
                [$userTest, $module] = $this->resolveSubmissionContext($validated);

                if ($request->has('elapsed_seconds')) {
                    $userTest->current_module_elapsed_seconds = (int) $request->input('elapsed_seconds');
                    $userTest->save();
                }

                return $this->saveModuleAnswers($userTest, $module, $validated['answers']);
            });

            return response()->json([
                'status' => 'success',
                'saved_count' => $savedCount,
                'message' => 'Answers autosaved.',
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'error' => 'Unauthorized autosave.',
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function checkScoringStatus(UserTest $userTest)
    {
        $this->authorize('view', $userTest);
        $cacheKey = "scoring_result_{$userTest->id}";

        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            $result = \Illuminate\Support\Facades\Cache::get($cacheKey);
            return response()->json($result);
        }

        return response()->json([
            'status' => 'scoring',
            'message' => 'Scoring in progress...',
        ]);
    }

    private function checkAnswer(Question $question, $userAnswer)
    {
        if ($question->question_type === 'multiple_choice') {
            $correctChoice = $question->answerChoices->where('is_correct', true)->first();
            return $correctChoice && trim($correctChoice->label) === trim($userAnswer);
        } else {
            // SPR
            $correctAnswers = $question->sprCorrectAnswers->pluck('answer')->map(fn($a) => trim($a))->toArray();
            return in_array(trim($userAnswer), $correctAnswers);
        }
    }

    private function resolveSubmissionContext(array $validated): array
    {
        $userTest = UserTest::where('id', $validated['user_test_id'])
            ->where('user_id', Auth::id())
            ->where('status', 'in_progress')
            ->first();

        if (!$userTest) {
            throw new AuthorizationException('This test attempt is no longer available.');
        }

        $module = Module::with(['section.test', 'questions'])->findOrFail($validated['module_id']);
        $section = $module->section;

        if (!$section || (int) $section->test_id !== (int) $userTest->test_id) {
            throw new AuthorizationException('This module does not belong to the active test attempt.');
        }

        $questionIds = collect(array_keys($validated['answers']))
            ->map(fn($id) => (string) $id);

        if ($questionIds->contains(fn($id) => !ctype_digit($id))) {
            throw ValidationException::withMessages([
                'answers' => 'Submitted answers contain invalid question ids.',
            ]);
        }

        $questionIds = $questionIds->map(fn($id) => (int) $id)->unique()->values();
        if ($questionIds->isNotEmpty()) {
            $validQuestionIds = $module->questions()
                ->whereIn('questions.id', $questionIds->all())
                ->pluck('questions.id')
                ->map(fn($id) => (int) $id);

            if ($validQuestionIds->count() !== $questionIds->count()) {
                throw ValidationException::withMessages([
                    'answers' => 'Submitted answers include questions outside the current module.',
                ]);
            }
        }

        return [$userTest, $module];
    }

    private function saveModuleAnswers(UserTest $userTest, Module $module, array $submittedAnswers): int
    {
        $questionIds = collect(array_keys($submittedAnswers))
            ->map(fn($id) => (int) $id)
            ->values();

        if ($questionIds->isEmpty()) {
            return 0;
        }

        $questions = Question::with(['answerChoices', 'sprCorrectAnswers'])
            ->whereIn('id', $questionIds->all())
            ->get()
            ->keyBy('id');

        $savedCount = 0;
        $upsertData = [];
        
        foreach ($submittedAnswers as $questionId => $answer) {
            $question = $questions->get((int) $questionId);
            if (!$question) {
                continue;
            }

            $normalizedAnswer = $answer === null ? null : trim((string) $answer);
            if ($normalizedAnswer === '') {
                $normalizedAnswer = null;
            }
            $isCorrect = $normalizedAnswer !== null && $normalizedAnswer !== ''
                ? $this->checkAnswer($question, $normalizedAnswer)
                : false;

            $row = [
                'user_test_id' => $userTest->id,
                'question_id' => (int) $questionId,
                'selected_answer' => $normalizedAnswer,
                'is_correct' => $isCorrect,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('user_test_answers', 'module_id')) {
                $row['module_id'] = $module->id;
            }

            $upsertData[] = $row;
            $savedCount++;
        }

        if (!empty($upsertData)) {
            $uniqueBy = Schema::hasColumn('user_test_answers', 'module_id')
                ? ['user_test_id', 'module_id', 'question_id']
                : ['user_test_id', 'question_id'];

            \App\Models\UserTestAnswer::upsert(
                $upsertData,
                $uniqueBy,
                ['selected_answer', 'is_correct', 'updated_at']
            );
        }

        return $savedCount;
    }

    private function showStaticPreview($type)
    {
        $testData = (object) [
            'id' => 99999,
            'page_title' => $type === 'math' ? 'Section 2, Module 1: Math' : 'Section 1, Module 1: Reading and Writing',
            'section_title' => $type === 'math' ? 'Math - Module 1' : 'Reading and Writing - Module 1',
            'section_number' => $type === 'math' ? 2 : 1,
            'module_number' => 1,
            'module_id' => $type === 'math' ? 99992 : 99991,
            'username' => auth()->user()?->username ?? 'Guest',
            'is_preview' => true,
            'duration_minutes' => 0,
        ];

        $questions = $this->getStaticPreviewQuestions($type);
        $viewName = $type === 'math' ? 'tests.take.math' : 'tests.take.reading';

        return view($viewName, [
            'testData' => $testData,
            'questions' => $questions,
            'currentQuestion' => 1,
            'totalQuestions' => $questions->count(),
            'sectionNumber' => $type === 'math' ? 2 : 1,
            'moduleNumber' => 1,
            'sectionName' => $type === 'math' ? 'Math' : 'Reading and Writing',
            'sectionType' => $type === 'math' ? 'math' : 'reading_writing',
            'nextModuleId' => $type === 'math' ? null : 'preview-math',
            'nextModuleName' => $type === 'math' ? null : 'Math Module 1',
            'userTestId' => null,
            'userTestUlid' => null,
            'userTest' => null,
            'savedAnswers' => collect(),
        ]);
    }

    private function getStaticPreviewQuestions($type)
    {
        if ($type === 'reading_writing') {
            $data = [
                [
                    'id' => 10001,
                    'stem' => 'Which choice completes the text with the most logical and precise word or phrase?',
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'medium',
                    'is_pretest' => false,
                    'section_type' => 'reading_writing',
                    'skill_domain' => 'craft_and_structure',
                    'passage' => (object)['content' => 'The spacecraft OSIRIS-REx briefly made contact with the asteroid 101955 Bennu in 2020. NASA scientist Daniella DellaGiustina reports that despite facing the unexpected obstacle of a surface mostly covered in boulders, OSIRIS-REx successfully ______ a sample of the surface, gathering pieces of it to bring back to Earth.'],
                    'answerChoices' => collect([
                        (object)['label' => 'A', 'content' => 'attached', 'is_correct' => false, 'order' => 1],
                        (object)['label' => 'B', 'content' => 'collected', 'is_correct' => true, 'order' => 2],
                        (object)['label' => 'C', 'content' => 'followed', 'is_correct' => false, 'order' => 3],
                        (object)['label' => 'D', 'content' => 'replaced', 'is_correct' => false, 'order' => 4],
                    ]),
                    'sprCorrectAnswers' => collect()
                ],
                [
                    'id' => 10002,
                    'stem' => 'Which choice completes the text with the most logical and precise word or phrase?',
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'medium',
                    'is_pretest' => false,
                    'section_type' => 'reading_writing',
                    'skill_domain' => 'information_and_ideas',
                    'passage' => (object)['content' => "Research conducted by planetary scientist Katarina Miljkovic suggests that the Moon's surface may not accurately ______ early impact events. When the Moon was still forming, its surface was softer, and asteroid or meteoroid impacts would have left less of an impression; thus, evidence of early impacts may no longer be present."],
                    'answerChoices' => collect([
                        (object)['label' => 'A', 'content' => 'reflect', 'is_correct' => true, 'order' => 1],
                        (object)['label' => 'B', 'content' => 'receive', 'is_correct' => false, 'order' => 2],
                        (object)['label' => 'C', 'content' => 'evaluate', 'is_correct' => false, 'order' => 3],
                        (object)['label' => 'D', 'content' => 'mimic', 'is_correct' => false, 'order' => 4],
                    ]),
                    'sprCorrectAnswers' => collect()
                ],
                [
                    'id' => 10003,
                    'stem' => 'Which choice best describes the function of the second sentence in the overall structure of the text?',
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'medium',
                    'is_pretest' => false,
                    'section_type' => 'reading_writing',
                    'skill_domain' => 'craft_and_structure',
                    'passage' => (object)['content' => 'Early twentieth-century architect Julia Morgan was known for her meticulous attention to detail and her ability to blend diverse architectural styles seamlessly. This versatility allowed her to design over 700 buildings, ranging from modest bungalows to the opulence of Hearst Castle, throughout her prolific career.'],
                    'answerChoices' => collect([
                        (object)['label' => 'A', 'content' => 'It provides a specific example of the diverse architectural styles mentioned in the first sentence.', 'is_correct' => false, 'order' => 1],
                        (object)['label' => 'B', 'content' => 'It explains how Morgan\'s reputation for meticulousness led to her receiving so many commissions.', 'is_correct' => false, 'order' => 2],
                        (object)['label' => 'C', 'content' => 'It illustrates the practical result of the versatility attributed to Morgan in the first sentence.', 'is_correct' => true, 'order' => 3],
                        (object)['label' => 'D', 'content' => 'It contrasts the modest designs of Morgan\'s early career with her later, more grand projects.', 'is_correct' => false, 'order' => 4],
                    ]),
                    'sprCorrectAnswers' => collect()
                ],
                [
                    'id' => 10004,
                    'stem' => 'Which choice completes the text so that it conforms to the conventions of Standard English?',
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'medium',
                    'is_pretest' => false,
                    'section_type' => 'reading_writing',
                    'skill_domain' => 'standard_english_conventions',
                    'passage' => (object)['content' => 'The team of archaeologists discovered a cache of ancient pottery shards during their excavation of the site; these fragments provided crucial evidence regarding the trade routes utilized by the civilization during its peak.'],
                    'answerChoices' => collect([
                        (object)['label' => 'A', 'content' => 'site; these', 'is_correct' => true, 'order' => 1],
                        (object)['label' => 'B', 'content' => 'site, these', 'is_correct' => false, 'order' => 2],
                        (object)['label' => 'C', 'content' => 'site. These', 'is_correct' => false, 'order' => 3],
                        (object)['label' => 'D', 'content' => 'site; These', 'is_correct' => false, 'order' => 4],
                    ]),
                    'sprCorrectAnswers' => collect()
                ],
                [
                    'id' => 10005,
                    'stem' => 'Which choice completes the text with the most logical transition?',
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'medium',
                    'is_pretest' => false,
                    'section_type' => 'reading_writing',
                    'skill_domain' => 'expression_of_ideas',
                    'passage' => (object)['content' => 'Many critics initially dismissed the composer\'s latest symphony as being too experimental and lacking a clear melodic structure. ______ subsequent performances have revealed a complex layering of themes that many now consider to be a masterpiece of modern orchestration.'],
                    'answerChoices' => collect([
                        (object)['label' => 'A', 'content' => 'Furthermore,', 'is_correct' => false, 'order' => 1],
                        (object)['label' => 'B', 'content' => 'Consequently,', 'is_correct' => false, 'order' => 2],
                        (object)['label' => 'C', 'content' => 'However,', 'is_correct' => true, 'order' => 3],
                        (object)['label' => 'D', 'content' => 'Similarly,', 'is_correct' => false, 'order' => 4],
                    ]),
                    'sprCorrectAnswers' => collect()
                ],
                [
                    'id' => 10006,
                    'stem' => 'Which choice best describes the data that would most strongly support the researcher\'s claim?',
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'medium',
                    'is_pretest' => true,
                    'section_type' => 'reading_writing',
                    'skill_domain' => 'information_and_ideas',
                    'passage' => (object)['content' => 'A researcher claims that the introduction of a new irrigation system in a drought-prone region significantly increased crop yields. The researcher points to data showing a 40% increase in wheat production in the three years following the system\'s installation compared to the previous decade\'s average.'],
                    'answerChoices' => collect([
                        (object)['label' => 'A', 'content' => 'A report showing that wheat prices remained stable during the installation period.', 'is_correct' => false, 'order' => 1],
                        (object)['label' => 'B', 'content' => 'Data showing that other regions without the new system saw no increase in wheat production.', 'is_correct' => true, 'order' => 2],
                        (object)['label' => 'C', 'content' => 'Evidence that the region experienced unusually high rainfall during the three-year period.', 'is_correct' => false, 'order' => 3],
                        (object)['label' => 'D', 'content' => 'A survey of local farmers expressing their satisfaction with the new irrigation technology.', 'is_correct' => false, 'order' => 4],
                    ]),
                    'sprCorrectAnswers' => collect()
                ]
            ];
        } else {
            $data = [
                [
                    'id' => 20001,
                    'stem' => 'If $$2x + 10 = 20$$, what is the value of $$4x$$?',
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'medium',
                    'is_pretest' => false,
                    'section_type' => 'math',
                    'skill_domain' => 'algebra',
                    'passage' => null,
                    'answerChoices' => collect([
                        (object)['label' => 'A', 'content' => '20', 'is_correct' => true, 'order' => 1],
                        (object)['label' => 'B', 'content' => '10', 'is_correct' => false, 'order' => 2],
                        (object)['label' => 'C', 'content' => '15', 'is_correct' => false, 'order' => 3],
                        (object)['label' => 'D', 'content' => '30', 'is_correct' => false, 'order' => 4],
                    ]),
                    'sprCorrectAnswers' => collect()
                ],
                [
                    'id' => 20002,
                    'stem' => 'What is the sum of the roots of the quadratic equation $$x^2 - 5x + 6 = 0$$?',
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'medium',
                    'is_pretest' => false,
                    'section_type' => 'math',
                    'skill_domain' => 'advanced_math',
                    'passage' => null,
                    'answerChoices' => collect([
                        (object)['label' => 'A', 'content' => '5', 'is_correct' => true, 'order' => 1],
                        (object)['label' => 'B', 'content' => '10', 'is_correct' => false, 'order' => 2],
                        (object)['label' => 'C', 'content' => '15', 'is_correct' => false, 'order' => 3],
                        (object)['label' => 'D', 'content' => '30', 'is_correct' => false, 'order' => 4],
                    ]),
                    'sprCorrectAnswers' => collect()
                ],
                [
                    'id' => 20003,
                    'stem' => 'A bag contains 3 red marbles and 2 blue marbles. If one marble is selected at random, what is the probability that the marble is red?',
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'medium',
                    'is_pretest' => false,
                    'section_type' => 'math',
                    'skill_domain' => 'problem_solving',
                    'passage' => null,
                    'answerChoices' => collect([
                        (object)['label' => 'A', 'content' => '3/5', 'is_correct' => true, 'order' => 1],
                        (object)['label' => 'B', 'content' => '10', 'is_correct' => false, 'order' => 2],
                        (object)['label' => 'C', 'content' => '15', 'is_correct' => false, 'order' => 3],
                        (object)['label' => 'D', 'content' => '30', 'is_correct' => false, 'order' => 4],
                    ]),
                    'sprCorrectAnswers' => collect()
                ],
                [
                    'id' => 20004,
                    'stem' => 'A circle has a radius of $$r = 3$$ units. What is the area of the circle in square units?',
                    'question_type' => 'multiple_choice',
                    'difficulty' => 'medium',
                    'is_pretest' => false,
                    'section_type' => 'math',
                    'skill_domain' => 'geometry',
                    'passage' => null,
                    'answerChoices' => collect([
                        (object)['label' => 'A', 'content' => '$$9\pi$$', 'is_correct' => true, 'order' => 1],
                        (object)['label' => 'B', 'content' => '10', 'is_correct' => false, 'order' => 2],
                        (object)['label' => 'C', 'content' => '15', 'is_correct' => false, 'order' => 3],
                        (object)['label' => 'D', 'content' => '30', 'is_correct' => false, 'order' => 4],
                    ]),
                    'sprCorrectAnswers' => collect()
                ],
                [
                    'id' => 20005,
                    'stem' => 'Solve for $$x$$: $$5x - 2 = 13$$',
                    'question_type' => 'student_produced_response',
                    'difficulty' => 'medium',
                    'is_pretest' => false,
                    'section_type' => 'math',
                    'skill_domain' => 'algebra',
                    'passage' => null,
                    'answerChoices' => collect(),
                    'sprCorrectAnswers' => collect([(object)['answer' => '3']]),
                    'spr_hint' => null
                ],
                [
                    'id' => 20006,
                    'stem' => 'If $$f(x) = x^2 + 4x$$, what is the value of $$f(2)$$?',
                    'question_type' => 'student_produced_response',
                    'difficulty' => 'medium',
                    'is_pretest' => true,
                    'section_type' => 'math',
                    'skill_domain' => 'advanced_math',
                    'passage' => null,
                    'answerChoices' => collect(),
                    'sprCorrectAnswers' => collect([(object)['answer' => '12']]),
                    'spr_hint' => null
                ]
            ];
        }
        return collect($data)->map(fn($item) => (object)$item);
    }
}
