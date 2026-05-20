<?php

namespace App\Http\Controllers;

use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Passage;
use App\Models\Question;
use App\Models\QuestionExplanation;
use App\Models\Section;
use App\Models\Test;
use App\Services\BulkQuestionCsvImportService;
use App\Services\BulkQuestionImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TestDashboardController extends Controller
{
    private const QUESTIONS_TABLE_PER_PAGE = 25;

    public function __construct() {}

    /**
     * Display the test data input dashboard.
     */
    public function index()
    {
        try {
            $tests = Test::with('sections.modules')->latest()->get();
        } catch (\Exception $e) {
            $tests = collect();
        }

        try {
            $passages = Passage::latest()->get();
        } catch (\Exception $e) {
            $passages = collect();
        }

        try {
            $questionsTotal = Question::query()->count();
            $questions = Question::query()
                ->select(['id', 'section_type', 'stem', 'is_pretest', 'is_complete', 'skill_domain', 'difficulty'])
                ->orderByDesc('id')
                ->limit(self::QUESTIONS_TABLE_PER_PAGE)
                ->get();
        } catch (\Exception $e) {
            $questionsTotal = 0;
            $questions = collect();
        }

        try {
            $allModules = Module::with('sections.test')->latest()->get();
        } catch (\Exception $e) {
            $allModules = collect();
        }

        $questionsPerPage = self::QUESTIONS_TABLE_PER_PAGE;

        return view('test-dashboard', compact('tests', 'passages', 'questions', 'questionsTotal', 'questionsPerPage', 'allModules'));
    }

    /**
     * JSON bundle of dashboard data for client-side refresh without a full page reload.
     */
    public function snapshot()
    {
        $tests = Test::with('sections.modules')->latest()->get();
        $passages = Passage::latest()->get();
        $allModules = Module::with('sections.test')->latest()->get();

        return response()->json([
            'tests' => $tests,
            'passages' => $passages,
            'allModules' => $allModules,
        ]);
    }

    /**
     * Paginated question rows for the dashboard table (lightweight columns only).
     */
    public function questionsList(Request $request)
    {
        $perPage = min(100, max(5, (int) $request->input('per_page', self::QUESTIONS_TABLE_PER_PAGE)));
        $page = max(1, (int) $request->input('page', 1));
        $q = trim((string) $request->input('q', ''));
        $sectionType = $request->input('section_type');
        $moduleId = $request->input('module_id');
        $isComplete = $request->input('is_complete');

        $query = Question::query()
            ->select(['questions.id', 'questions.section_type', 'questions.stem', 'questions.is_pretest', 'questions.is_complete', 'questions.skill_domain', 'questions.difficulty'])
            ->orderByDesc('questions.id');

        if ($q !== '') {
            if (ctype_digit($q)) {
                $query->where('questions.id', (int) $q);
            } else {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
                $query->where('questions.stem', 'like', $like);
            }
        }

        if (in_array($sectionType, ['reading_writing', 'math'], true)) {
            $query->where('questions.section_type', $sectionType);
        }

        if ($isComplete === '0' || $isComplete === 'false') {
            $query->where('questions.is_complete', false);
        } elseif ($isComplete === '1' || $isComplete === 'true') {
            $query->where('questions.is_complete', true);
        }

        if ($moduleId && ctype_digit((string) $moduleId)) {
            $query->join('module_questions', 'questions.id', '=', 'module_questions.question_id')
                  ->where('module_questions.module_id', (int) $moduleId)
                  ->addSelect('module_questions.position as question_number')
                  ->reorder('module_questions.position', 'asc');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    /**
     * Tom Select remote options: latest / search by stem / optional pinned id.
     *
     * @return array<int, array{value: string, text: string}>
     */
    private function formatQuestionSearchResults(\Illuminate\Support\Collection $rows): array
    {
        return $rows->map(fn (Question $row): array => [
            'value' => (string) $row->id,
            'text' => 'ID:'.$row->id.' - '.Str::limit(strip_tags((string) $row->stem), 50),
        ])->values()->all();
    }

    /**
     * Remote search for question pickers (answer choices, explanations).
     */
    public function questionsSearch(Request $request)
    {
        $id = $request->input('id');
        $q = trim((string) $request->input('q', ''));
        $limit = min(50, max(5, (int) $request->input('limit', 20)));

        $pinned = null;
        if ($id !== null && $id !== '' && ctype_digit((string) $id)) {
            $pinned = Question::query()->select(['id', 'stem'])->find((int) $id);
        }

        $query = Question::query()->select(['id', 'stem'])->orderByDesc('id');
        if ($pinned) {
            $query->where('id', '!=', $pinned->id);
        }

        if ($q !== '') {
            if (ctype_digit($q)) {
                $query->where('id', (int) $q);
            } else {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
                $query->where('stem', 'like', $like);
            }
        }

        $take = $limit - ($pinned ? 1 : 0);
        $rows = $take > 0 ? $query->limit($take)->get() : collect();

        $merged = collect();
        if ($pinned) {
            $merged->push($pinned);
        }
        $merged = $merged->concat($rows)->unique('id')->take($limit);

        return response()->json([
            'data' => $this->formatQuestionSearchResults($merged),
        ]);
    }

    /**
     * Store a new test.
     */
    public function storeTest(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'test_type' => 'required|in:full_length,section_only,mini_quiz',
            'break_duration_minutes' => 'required|integer|min:0',
            'status' => 'required|in:draft,active,archived',
        ]);

        $validated['total_duration_minutes'] = 0;
        $test = Test::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Test created successfully',
            'data' => $test,
        ], 201);
    }

    /**
     * Update an existing test.
     */
    public function updateTest(Request $request, $id)
    {
        $test = Test::findOrFail($id);
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'test_type' => 'sometimes|required|in:full_length,section_only,mini_quiz',
            'break_duration_minutes' => 'sometimes|required|integer|min:0',
            'status' => 'sometimes|required|in:draft,active,archived',
        ]);
        $test->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Test updated successfully',
            'data' => $test,
        ]);
    }

    /**
     * Store a new section.
     */
    public function storeSection(Request $request)
    {
        $validated = $request->validate([
            'test_id' => 'required|exists:tests,id',
            'name' => 'nullable|string|max:255',
            'type' => 'required|in:reading_writing,math',
        ]);

        if (Section::where('test_id', $validated['test_id'])->where('type', $validated['type'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This test already has a section for that type.',
            ], 422);
        }

        $validated['order'] = $validated['type'] === 'reading_writing' ? 1 : 2;
        if (empty($validated['name'])) {
            $validated['name'] = $validated['type'] === 'reading_writing' ? 'Reading and Writing' : 'Math';
        }
        $section = Section::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Section created successfully',
            'data' => $section,
        ], 201);
    }

    public function storeModule(Request $request)
    {
        $validated = $request->validate([
            'section_id' => 'nullable|exists:sections,id',
            'test_id' => 'nullable|exists:tests,id',
            'section_type' => 'nullable|in:reading_writing,math',
            'key' => 'nullable|string|unique:modules,key|max:255',
            'module_number' => 'required|integer|min:1',
            'difficulty_level' => 'required|in:standard,easy,hard',
            'duration_minutes' => 'required|integer|min:1',
            'total_questions' => 'required|integer|min:1',
        ]);

        // Auto-generate section if test_id and section_type are provided and section_id is empty
        if (empty($validated['section_id']) && !empty($validated['test_id']) && !empty($validated['section_type'])) {
            $section = Section::firstOrCreate([
                'test_id' => $validated['test_id'],
                'type' => $validated['section_type'],
            ], [
                'name' => $validated['section_type'] === 'reading_writing' ? 'Reading and Writing' : 'Math',
                'order' => $validated['section_type'] === 'reading_writing' ? 1 : 2,
            ]);
            $validated['section_id'] = $section->id;
        }

        if (!empty($validated['section_id'])) {
            $section = Section::findOrFail($validated['section_id']);
            $baseOrder = (($section->order - 1) * 2) + (int) $validated['module_number'];
            $existingMax = Module::where('section_id', $section->id)
                ->where('module_number', $validated['module_number'])
                ->max('order');
            $validated['order'] = $existingMax !== null ? ((int) $existingMax + 1) : $baseOrder;
        } else {
            $validated['order'] = 1;
        }

        // Generate unique key if empty
        if (empty($validated['key'])) {
            $validated['key'] = 'MOD_' . strtoupper(Str::random(8));
        }

        $module = Module::create($validated);

        if (!empty($validated['section_id'])) {
            // Link in the pivot table
            $module->sections()->syncWithoutDetaching([$validated['section_id']]);
            
            if ($module->section && $module->section->test) {
                $module->section->test->refreshTotalDuration();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Module created successfully',
            'data' => $module,
        ], 201);
    }

    /**
     * Link an existing reusable module to a section.
     */
    public function linkModuleToSection(Request $request)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
            'section_id' => 'nullable|exists:sections,id',
            'test_id' => 'nullable|exists:tests,id',
            'section_type' => 'nullable|in:reading_writing,math',
        ]);

        $module = Module::findOrFail($validated['module_id']);

        if (empty($validated['section_id'])) {
            if (empty($validated['test_id']) || empty($validated['section_type'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Either Section ID or Test ID + Section Type is required.',
                ], 422);
            }

            // Find or create section
            $section = Section::firstOrCreate([
                'test_id' => $validated['test_id'],
                'type' => $validated['section_type'],
            ], [
                'name' => $validated['section_type'] === 'reading_writing' ? 'Reading and Writing' : 'Math',
                'order' => $validated['section_type'] === 'reading_writing' ? 1 : 2,
            ]);
        } else {
            $section = Section::findOrFail($validated['section_id']);
        }

        if ($section->modules()->where('module_id', $module->id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This module is already linked to this section.',
            ], 422);
        }

        $section->modules()->attach($module->id);

        if ($section->test) {
            $section->test->refreshTotalDuration();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Module linked successfully!',
        ]);
    }

    /**
     * Fetch a single question with its passage and choices.
     */
    public function showQuestion($id)
    {
        $question = Question::with(['passage', 'answerChoices', 'explanation', 'sprCorrectAnswers'])->findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $question,
        ]);
    }

    /**
     * Update an existing question and its associated passage (if R&W).
     */
    public function updateQuestion(Request $request, $id)
    {
        $question = Question::with(['passage', 'answerChoices', 'explanation'])->findOrFail($id);

        // Normalize spr_answers
        if ($request->has('spr_answers')) {
            $val = $request->input('spr_answers');
            if (is_array($val)) {
                $request->merge(['spr_answers' => implode(', ', array_filter($val))]);
            } elseif ($val === null) {
                $request->merge(['spr_answers' => '']);
            }
        } else {
            // Ensure spr_answers is at least an empty string if it's an SPR question to satisfy required_if
            if ($request->input('question_type') === 'student_produced_response') {
                $request->merge(['spr_answers' => '']);
            }
        }

        $validated = $request->validate([
            'stem' => 'required|string',
            'question_type' => 'required|in:multiple_choice,student_produced_response',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'skill_domain' => 'nullable|string|max:255',
            'skill_subdomain' => 'nullable|string|max:255',
            'spr_hint' => 'nullable|string',
            'is_pretest' => 'boolean',
            'calculator_allowed' => 'boolean',
            'passage_content' => 'nullable|string',
            
            // Choices & SPR & Explanation
            'correct_choice' => 'required_if:question_type,multiple_choice|string|max:1',
            'choices' => 'required_if:question_type,multiple_choice|array',
            'spr_answers' => 'nullable|string', // Changed from required_if to nullable for smoother validation
            'explanation' => 'nullable|string',
            'rationale_a' => 'nullable|string',
            'rationale_b' => 'nullable|string',
            'rationale_c' => 'nullable|string',
            'rationale_d' => 'nullable|string',
        ]);

        // Manually check SPR requirement if type is SPR
        if ($validated['question_type'] === 'student_produced_response' && empty($validated['spr_answers'])) {
             return response()->json([
                'message' => 'The spr answers field is required for Student Produced Response questions.',
                'errors' => ['spr_answers' => ['The spr answers field is required.']]
            ], 422);
        }

        DB::transaction(function () use ($question, $validated) {
            $isComplete = !empty($validated['difficulty']) && !empty($validated['skill_domain']);

            $question->update([
                'stem' => $validated['stem'],
                'question_type' => $validated['question_type'],
                'difficulty' => $validated['difficulty'] ?? $question->difficulty,
                'skill_domain' => $validated['skill_domain'] ?? $question->skill_domain,
                'skill_subdomain' => $validated['skill_subdomain'] ?? $question->skill_subdomain,
                'spr_hint' => $validated['spr_hint'] ?? $question->spr_hint,
                'is_pretest' => $validated['is_pretest'] ?? $question->is_pretest,
                'calculator_allowed' => $validated['calculator_allowed'] ?? $question->calculator_allowed,
                'is_complete' => $isComplete,
            ]);

            if ($question->section_type === 'reading_writing' && $question->passage_id && isset($validated['passage_content'])) {
                $question->passage->update([
                    'content' => $validated['passage_content']
                ]);
            }

            // Update choices
            if ($validated['question_type'] === 'multiple_choice') {
                foreach ($validated['choices'] as $choiceData) {
                    $question->answerChoices()->updateOrCreate(
                        ['label' => $choiceData['label']],
                        [
                            'content' => $choiceData['content'],
                            'is_correct' => $choiceData['label'] === $validated['correct_choice'],
                            'order' => array_search($choiceData['label'], ['A', 'B', 'C', 'D']) + 1
                        ]
                    );
                }
            } else {
                // Update SPR answers
                DB::table('spr_correct_answers')->where('question_id', $question->id)->delete();
                $answers = array_map('trim', explode(',', $validated['spr_answers']));
                foreach ($answers as $ans) {
                    if ($ans === '') continue;
                    DB::table('spr_correct_answers')->insert([
                        'question_id' => $question->id,
                        'answer' => $ans,
                        'answer_type' => 'exact',
                        'created_at' => now(),
                    ]);
                }
            }

            // Update Explanation
            if (isset($validated['explanation'])) {
                $question->explanation()->updateOrCreate(
                    ['question_id' => $question->id],
                    [
                        'explanation' => $validated['explanation'],
                        'rationale_a' => $validated['rationale_a'] ?? null,
                        'rationale_b' => $validated['rationale_b'] ?? null,
                        'rationale_c' => $validated['rationale_c'] ?? null,
                        'rationale_d' => $validated['rationale_d'] ?? null,
                    ]
                );
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Question updated successfully',
            'data' => $question->fresh(['passage', 'answerChoices', 'explanation', 'sprCorrectAnswers']),
        ]);
    }

    /**
     * Attach an existing question to a module.
     */
    public function attachQuestionToModule(Request $request)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
            'question_id' => 'required|exists:questions,id',
            'position' => 'nullable|integer|min:1',
        ]);

        $module = Module::findOrFail($validated['module_id']);
        if ($module->questions()->where('question_id', $validated['question_id'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Question is already attached to this module.',
            ], 422);
        }

        $position = $validated['position'];
        if (empty($position)) {
            $max = (int) DB::table('module_questions')->where('module_id', $module->id)->max('position');
            $position = $max + 1;
        }

        // Auto-shift: if position taken, push existing ones forward
        DB::transaction(function () use ($module, $position) {
            DB::table('module_questions')
                ->where('module_id', $module->id)
                ->where('position', '>=', $position)
                ->increment('position');
        });

        $module->questions()->attach($validated['question_id'], ['position' => $position]);

        return response()->json([
            'status' => 'success',
            'message' => 'Question attached to module successfully (positions auto-shifted if needed).',
        ]);
    }

    /**
     * Preview questions from JSON without saving.
     */
    public function bulkPreviewQuestions(Request $request, BulkQuestionImportService $bulkQuestionImport)
    {
        $payload = $bulkQuestionImport->buildPayloadFromRequest($request);
        $validated = $bulkQuestionImport->validate($payload);
        return response()->json(['status' => 'success', 'data' => ['items' => $validated['items'] ?? [], 'module_id' => $validated['module_id'] ?? null]]);
    }

    /**
     * Create multiple questions in one request.
     */
    public function bulkStoreQuestions(Request $request, BulkQuestionImportService $bulkQuestionImport)
    {
        $payload = $bulkQuestionImport->buildPayloadFromRequest($request);
        $result = $bulkQuestionImport->import($payload);
        return response()->json(['status' => 'success', 'message' => count($result['question_ids']).' question(s) created.', 'data' => $result], 201);
    }

    /**
     * Preview questions from CSV without saving.
     */
    public function bulkPreviewQuestionsFromCsv(Request $request, BulkQuestionCsvImportService $csvImport, BulkQuestionImportService $bulkQuestionImport)
    {
        $request->validate(['csv_file' => 'required|file|max:5120', 'module_id' => 'nullable|exists:modules,id']);
        $file = $request->file('csv_file');
        $raw = (string) file_get_contents($file->getRealPath());
        $items = $csvImport->parseCsvToItems($raw);
        $moduleId = $request->input('module_id');
        if ($moduleId) {
            $validated = $bulkQuestionImport->validate(['module_id' => $moduleId, 'start_position' => 1, 'items' => $items]);
            $items = $validated['items'];
        }
        return response()->json(['status' => 'success', 'data' => ['items' => $items]]);
    }

    /**
     * Bulk import from CSV.
     */
    public function bulkStoreQuestionsFromCsv(Request $request, BulkQuestionCsvImportService $csvImport, BulkQuestionImportService $bulkQuestionImport)
    {
        $payload = $csvImport->getPayloadFromRequest($request);
        $result = $bulkQuestionImport->import($payload);
        return response()->json(['status' => 'success', 'message' => count($result['question_ids']).' question(s) created.', 'data' => $result], 201);
    }

    /**
     * Bulk import from ZIP (JSON/CSV + Images).
     */
    public function bulkStoreQuestionsFromZip(Request $request, BulkQuestionImportService $bulkQuestionImport)
    {
        try {
            $result = $bulkQuestionImport->importFromZip($request);
            return response()->json([
                'status' => 'success',
                'message' => count($result['question_ids']).' question(s) created.',
                'data' => $result
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'ZIP Import Failed: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Delete endpoints
     */
    public function deleteTest(Request $request, $id)
    {
        $test = Test::with('sections.modules.questions')->findOrFail($id);
        
        DB::transaction(function () use ($test, $request) {
            if ($request->boolean('delete_children')) {
                foreach ($test->sections as $section) {
                    foreach ($section->modules as $module) {
                        foreach ($module->questions as $question) {
                            $question->delete();
                        }
                        $module->delete();
                    }
                    $section->delete();
                }
            }
            $test->delete();
        });

        return response()->json(['status' => 'success', 'message' => 'Test deleted.']);
    }

    public function deleteSection(Request $request, $id)
    {
        $section = Section::with(['test', 'modules.questions'])->findOrFail($id);
        $test = $section->test;

        DB::transaction(function () use ($section, $request) {
            if ($request->boolean('delete_children')) {
                foreach ($section->modules as $module) {
                    foreach ($module->questions as $question) {
                        $question->delete();
                    }
                    $module->delete();
                }
            }
            $section->delete();
        });

        if ($test) $test->refreshTotalDuration();
        return response()->json(['status' => 'success', 'message' => 'Section deleted.']);
    }

    public function deleteModule(Request $request, $id)
    {
        $module = Module::with(['section.test', 'questions'])->findOrFail($id);
        $test = $module->section->test ?? null;

        DB::transaction(function () use ($module, $request) {
            if ($request->boolean('delete_children')) {
                foreach ($module->questions as $question) {
                    $question->delete();
                }
            }
            $module->delete();
        });

        if ($test) $test->refreshTotalDuration();
        return response()->json(['status' => 'success', 'message' => 'Module deleted.']);
    }

    public function deleteQuestion($id)
    {
        Question::findOrFail($id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Question deleted.']);
    }
}
