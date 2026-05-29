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
use App\Services\TestManagementService;
use App\Http\Requests\StoreTestRequest;
use App\Http\Requests\UpdateTestRequest;
use App\Http\Requests\StoreSectionRequest;
use App\Http\Requests\UpdateSectionRequest;
use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Http\Requests\LinkModuleRequest;
use App\Http\Requests\UpdateQuestionRequest;
use App\Http\Requests\AttachQuestionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TestDashboardController extends Controller
{
    private const QUESTIONS_TABLE_PER_PAGE = 30;

    protected TestManagementService $testManagement;

    public function __construct(TestManagementService $testManagement)
    {
        $this->testManagement = $testManagement;
    }

    /**
     * Display the test data input dashboard.
     */
    public function index()
    {
        try {
            $tests = Test::visibleTo(auth()->user())->with(['creator', 'sections.creator', 'sections.modules.creator'])->latest()->get();
        } catch (\Exception $e) {
            $tests = collect();
        }

        try {
            $passages = Passage::latest()->get();
        } catch (\Exception $e) {
            $passages = collect();
        }

        try {
            $qQuery = Question::visibleTo(auth()->user());
            if (auth()->user()->role === 'teacher') {
                $qQuery->where('created_by', auth()->id());
            }
            $questionsTotal = $qQuery->count();
            $questions = $qQuery
                ->select(['id', 'section_type', 'stem', 'is_pretest', 'is_complete', 'skill_domain', 'difficulty', 'created_by'])
                ->orderByDesc('id')
                ->limit(self::QUESTIONS_TABLE_PER_PAGE)
                ->get();
        } catch (\Exception $e) {
            $questionsTotal = 0;
            $questions = collect();
        }

        try {
            $allModules = Module::visibleTo(auth()->user())->with(['creator', 'sections.test'])->latest()->get();
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
        $tests = Test::visibleTo(auth()->user())->with(['creator', 'sections.creator', 'sections.modules.creator'])->latest()->get();
        $passages = Passage::latest()->get();
        $allModules = Module::visibleTo(auth()->user())->with(['creator', 'sections.test'])->latest()->get();

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

        $showShared = $request->boolean('show_shared', false);

        $query = Question::visibleTo(auth()->user());
        if (auth()->user()->role === 'teacher' && !$showShared) {
            $query->where('questions.created_by', auth()->id());
        }
        $query->select(['questions.id', 'questions.section_type', 'questions.stem', 'questions.is_pretest', 'questions.is_complete', 'questions.skill_domain', 'questions.difficulty', 'questions.created_by']);

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
        } else {
            $query->orderByRaw('CASE WHEN questions.created_by = ? THEN 0 ELSE 1 END ASC', [auth()->id()])
                  ->orderByDesc('questions.id');
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
            $pinned = Question::visibleTo(auth()->user())->select(['id', 'stem'])->find((int) $id);
        }

        $query = Question::visibleTo(auth()->user())->select(['id', 'stem'])->orderByDesc('id');
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
    public function storeTest(StoreTestRequest $request)
    {
        $validated = $request->validated();
        $validated['total_duration_minutes'] = 0;
        $validated['created_by'] = auth()->id();
        $validated['is_public'] = $request->boolean('is_public', false);
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
    public function updateTest(UpdateTestRequest $request, $id)
    {
        $test = Test::findOrFail($id);
        if (auth()->user()->role === 'teacher' && $test->created_by !== auth()->id()) {
            abort(403, 'Unauthorized. You do not own this resource.');
        }
        $validated = $request->validated();
        if (isset($validated['is_public'])) {
            $validated['is_public'] = filter_var($validated['is_public'], FILTER_VALIDATE_BOOLEAN);
        }
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
    public function storeSection(StoreSectionRequest $request)
    {
        $validated = $request->validated();

        $test = Test::findOrFail($validated['test_id']);
        if (auth()->user()->role === 'teacher' && $test->created_by !== auth()->id()) {
            abort(403, 'Unauthorized. You do not own the parent test.');
        }

        if (Section::where('test_id', $validated['test_id'])->where('type', $validated['type'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This test already has a section for that type.',
            ], 422);
        }

        $validated['order'] = $validated['type'] === Section::TYPE_RW ? 1 : 2;
        if (empty($validated['name'])) {
            $validated['name'] = $validated['type'] === Section::TYPE_RW ? 'Reading and Writing' : 'Math';
        }
        $validated['created_by'] = auth()->id();
        $validated['is_public'] = $request->boolean('is_public', false);
        $section = Section::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Section created successfully',
            'data' => $section,
        ], 201);
    }

    /**
     * Update an existing section.
     */
    public function updateSection(UpdateSectionRequest $request, $id)
    {
        $section = Section::findOrFail($id);
        if (auth()->user()->role === 'teacher' && $section->created_by !== auth()->id()) {
            abort(403, 'Unauthorized. You do not own this resource.');
        }
        $validated = $request->validated();
        if (isset($validated['is_public'])) {
            $validated['is_public'] = filter_var($validated['is_public'], FILTER_VALIDATE_BOOLEAN);
        }
        $section->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Section updated successfully',
            'data' => $section,
        ]);
    }

    public function storeModule(StoreModuleRequest $request)
    {
        $validated = $request->validated();

        // Auto-generate section if test_id and section_type are provided and section_id is empty
        if (empty($validated['section_id']) && !empty($validated['test_id']) && !empty($validated['section_type'])) {
            $test = Test::findOrFail($validated['test_id']);
            if (auth()->user()->role === 'teacher' && $test->created_by !== auth()->id()) {
                abort(403, 'Unauthorized. You do not own the parent test.');
            }

            $section = Section::firstOrCreate([
                'test_id' => $validated['test_id'],
                'type' => $validated['section_type'],
            ], [
                'name' => $validated['section_type'] === Section::TYPE_RW ? 'Reading and Writing' : 'Math',
                'order' => $validated['section_type'] === Section::TYPE_RW ? 1 : 2,
                'created_by' => auth()->id(),
                'is_public' => $request->boolean('is_public', false),
            ]);
            $validated['section_id'] = $section->id;
        }

        if (!empty($validated['section_id'])) {
            $section = Section::findOrFail($validated['section_id']);
            if (auth()->user()->role === 'teacher' && $section->created_by !== auth()->id()) {
                abort(403, 'Unauthorized. You do not own the parent section.');
            }

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

        $validated['created_by'] = auth()->id();
        $validated['is_public'] = $request->boolean('is_public', false);
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
     * Update an existing module.
     */
    public function updateModule(UpdateModuleRequest $request, $id)
    {
        $module = Module::findOrFail($id);
        if (auth()->user()->role === 'teacher' && $module->created_by !== auth()->id()) {
            abort(403, 'Unauthorized. You do not own this resource.');
        }
        $validated = $request->validated();
        if (isset($validated['is_public'])) {
            $validated['is_public'] = filter_var($validated['is_public'], FILTER_VALIDATE_BOOLEAN);
        }
        $module->update($validated);
        
        if ($module->section && $module->section->test) {
            $module->section->test->refreshTotalDuration();
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Module updated successfully',
            'data' => $module,
        ]);
    }

    /**
     * Link an existing reusable module to a section.
     */
    public function linkModuleToSection(LinkModuleRequest $request)
    {
        $validated = $request->validated();

        $module = Module::findOrFail($validated['module_id']);
        if (auth()->user()->role === 'teacher') {
            if ($module->created_by !== auth()->id() && !$module->is_public && !($module->section && $module->section->is_public)) {
                // Wait, our recursively cascaded check is visibleTo scope.
                // Let's just check using visibleTo on the model:
                if (!Module::visibleTo(auth()->user())->where('id', $module->id)->exists()) {
                    abort(403, 'Unauthorized. This module is private.');
                }
            }
        }

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
                'name' => $validated['section_type'] === Section::TYPE_RW ? 'Reading and Writing' : 'Math',
                'order' => $validated['section_type'] === Section::TYPE_RW ? 1 : 2,
                'created_by' => auth()->id(),
                'is_public' => false,
            ]);
        } else {
            $section = Section::findOrFail($validated['section_id']);
        }

        if (auth()->user()->role === 'teacher' && $section->created_by !== auth()->id()) {
            abort(403, 'Unauthorized. You do not own this section.');
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
        $question = Question::visibleTo(auth()->user())->with(['passage', 'answerChoices', 'explanation', 'sprCorrectAnswers'])->findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $question,
        ]);
    }

    /**
     * Update an existing question and its associated passage (if R&W).
     */
    public function updateQuestion(UpdateQuestionRequest $request, $id)
    {
        $question = Question::with(['passage', 'answerChoices', 'explanation'])->findOrFail($id);
        if (auth()->user()->role === 'teacher' && $question->created_by !== auth()->id()) {
            abort(403, 'Unauthorized. You do not own this question.');
        }
        $validated = $request->validated();

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

            if ($question->section_type === Section::TYPE_RW && $question->passage_id && isset($validated['passage_content'])) {
                $question->passage->update([
                    'content' => $validated['passage_content']
                ]);
            }

            // Update choices
            if ($validated['question_type'] === Question::TYPE_MCQ) {
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
    public function attachQuestionToModule(AttachQuestionRequest $request)
    {
        $validated = $request->validated();

        $module = Module::findOrFail($validated['module_id']);
        if (auth()->user()->role === 'teacher' && $module->created_by !== auth()->id()) {
            abort(403, 'Unauthorized. You do not own this module.');
        }

        // Validate the question is visible to the user
        $question = Question::visibleTo(auth()->user())->findOrFail($validated['question_id']);

        if ($module->questions()->where('question_id', $question->id)->exists()) {
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

        $module->questions()->attach($question->id, ['position' => $position]);

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
        try {
            $validated = $bulkQuestionImport->validate($payload);
            $items = $validated['items'] ?? [];
            foreach ($items as &$item) {
                $item['errors'] = [];
            }
            return response()->json(['status' => 'success', 'data' => ['items' => $items, 'module_id' => $validated['module_id'] ?? null]]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $items = $payload['items'] ?? [];
            $errors = $e->errors();
            foreach ($items as $index => &$item) {
                $item['errors'] = $item['errors'] ?? [];
                $prefix = "items.{$index}.";
                foreach ($errors as $key => $messages) {
                    if (str_starts_with($key, $prefix)) {
                        foreach ($messages as $msg) {
                            $item['errors'][] = $msg;
                        }
                    }
                }
            }
            return response()->json(['status' => 'success', 'data' => ['items' => $items, 'module_id' => $payload['module_id'] ?? null]]);
        }
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
        $items = $csvImport->parseCsvToItems($raw, true);
        $moduleId = $request->input('module_id');
        if ($moduleId) {
            try {
                $validated = $bulkQuestionImport->validate(['module_id' => $moduleId, 'start_position' => 1, 'items' => $items]);
                $items = $validated['items'] ?? [];
                foreach ($items as &$item) {
                    $item['errors'] = $item['errors'] ?? [];
                }
            } catch (\Illuminate\Validation\ValidationException $e) {
                $errors = $e->errors();
                foreach ($items as $index => &$item) {
                    $item['errors'] = $item['errors'] ?? [];
                    $prefix = "items.{$index}.";
                    foreach ($errors as $key => $messages) {
                        if (str_starts_with($key, $prefix)) {
                            foreach ($messages as $msg) {
                                $item['errors'][] = $msg;
                            }
                        }
                    }
                }
            }
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
     * Auto-generate full SAT structure safely using transactions.
     */
    public function generateFullSatStructure(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'test_type' => 'sometimes|string|in:full_length,short_test,module_only',
        ]);

        $testType = $validated['test_type'] ?? 'full_length';

        try {
            $test = $this->testManagement->generateFullSatStructure($validated['title'], $testType, auth()->id());

            return response()->json([
                'status' => 'success',
                'message' => 'SAT Structure created successfully.',
                'data' => $test
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate structure: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clone a Test (Hierarchy only).
     */
    public function cloneTest(Request $request, $id)
    {
        // Check visibility first
        $originalTest = Test::visibleTo(auth()->user())->findOrFail($id);

        try {
            $test = $this->testManagement->cloneTest((int) $id, auth()->id());

            return response()->json([
                'status' => 'success',
                'message' => 'Test cloned successfully.',
                'data' => $test
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clone test: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clone a Module (Hierarchy only).
     */
    public function cloneModule(Request $request, $id)
    {
        // Check visibility first
        $originalModule = Module::visibleTo(auth()->user())->findOrFail($id);

        $sectionId = $request->input('section_id');
        if ($sectionId) {
            $section = Section::findOrFail($sectionId);
            if (auth()->user()->role === 'teacher' && $section->created_by !== auth()->id()) {
                abort(403, 'Unauthorized. You do not own the target section.');
            }
        }

        try {
            $module = $this->testManagement->cloneModule((int) $id, $sectionId ? (int) $sectionId : null, auth()->id());

            return response()->json([
                'status' => 'success',
                'message' => 'Module cloned successfully.',
                'data' => $module
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clone module: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete endpoints
     */
    public function deleteTest(Request $request, $id)
    {
        $test = Test::findOrFail($id);
        if (auth()->user()->role === 'teacher' && $test->created_by !== auth()->id()) {
            abort(403, 'Unauthorized. You do not own this test.');
        }

        try {
            $this->testManagement->deleteTest((int) $id, $request->boolean('delete_children'));
            return response()->json(['status' => 'success', 'message' => 'Test deleted.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteSection(Request $request, $id)
    {
        $section = Section::findOrFail($id);
        if (auth()->user()->role === 'teacher' && $section->created_by !== auth()->id()) {
            abort(403, 'Unauthorized. You do not own this section.');
        }

        try {
            $this->testManagement->deleteSection((int) $id, $request->boolean('delete_children'));
            return response()->json(['status' => 'success', 'message' => 'Section deleted.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteModule(Request $request, $id)
    {
        $module = Module::findOrFail($id);
        if (auth()->user()->role === 'teacher' && $module->created_by !== auth()->id()) {
            abort(403, 'Unauthorized. You do not own this module.');
        }

        try {
            $this->testManagement->deleteModule((int) $id, $request->boolean('delete_children'));
            return response()->json(['status' => 'success', 'message' => 'Module deleted.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteQuestion($id)
    {
        $question = Question::findOrFail($id);
        if (auth()->user()->role === 'teacher' && $question->created_by !== auth()->id()) {
            abort(403, 'Unauthorized. You do not own this question.');
        }

        $question->delete();
        return response()->json(['status' => 'success', 'message' => 'Question deleted.']);
    }
}

