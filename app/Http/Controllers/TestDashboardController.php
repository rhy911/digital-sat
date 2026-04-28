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
use App\Services\AiClassificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TestDashboardController extends Controller
{
    private const QUESTIONS_TABLE_PER_PAGE = 25;

    public function __construct(
        private AiClassificationService $aiClassification
    ) {}

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
                ->select(['id', 'section_type', 'stem', 'is_pretest', 'skill_domain'])
                ->orderByDesc('id')
                ->limit(self::QUESTIONS_TABLE_PER_PAGE)
                ->get();
        } catch (\Exception $e) {
            $questionsTotal = 0;
            $questions = collect();
        }

        $questionsPerPage = self::QUESTIONS_TABLE_PER_PAGE;

        return view('test-dashboard', compact('tests', 'passages', 'questions', 'questionsTotal', 'questionsPerPage'));
    }

    /**
     * JSON bundle of dashboard data for client-side refresh without a full page reload.
     */
    public function snapshot()
    {
        $tests = Test::with('sections.modules')->latest()->get();
        $passages = Passage::latest()->get();

        return response()->json([
            'tests' => $tests,
            'passages' => $passages,
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

        $query = Question::query()
            ->select(['id', 'section_type', 'stem', 'is_pretest', 'skill_domain'])
            ->orderByDesc('id');

        if ($q !== '') {
            if (ctype_digit($q)) {
                $query->where('id', (int) $q);
            } else {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
                $query->where('stem', 'like', $like);
            }
        }

        if (in_array($sectionType, ['reading_writing', 'math'], true)) {
            $query->where('section_type', $sectionType);
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

    /**
     * Store a new module.
     */
    public function storeModule(Request $request)
    {
        $validated = $request->validate([
            'section_id' => 'required|exists:sections,id',
            'module_number' => 'required|integer|min:1',
            'difficulty_level' => 'required|in:standard,easy,hard',
            'duration_minutes' => 'required|integer|min:1',
            'total_questions' => 'required|integer|min:1',
        ]);

        $section = Section::findOrFail($validated['section_id']);
        $baseOrder = (($section->order - 1) * 2) + (int) $validated['module_number'];
        $existingMax = Module::where('section_id', $section->id)
            ->where('module_number', $validated['module_number'])
            ->max('order');
        $validated['order'] = $existingMax !== null ? ((int) $existingMax + 1) : $baseOrder;

        $module = Module::create($validated);
        if ($module->section && $module->section->test) {
            $module->section->test->refreshTotalDuration();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Module created successfully',
            'data' => $module,
        ], 201);
    }

    /**
     * Store a new passage.
     */
    public function storePassage(Request $request)
    {
        $payload = $request->all();
        foreach (['word_count', 'source_year'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] === '') {
                $payload[$key] = null;
            }
        }

        $validated = validator($payload, [
            'content' => 'required|string',
            'passage_type' => 'required|in:single,paired',
            'word_count' => 'nullable|integer|min:0',
            'source_title' => 'nullable|string|max:255',
            'source_author' => 'nullable|string|max:255',
            'source_year' => 'nullable|integer',
            'genre' => 'nullable|in:literary_narrative,social_science,natural_science,humanities',
        ])->validate();

        $passage = Passage::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Passage created successfully',
            'data' => $passage,
        ], 201);
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
     * Store a new question.
     */
    public function storeQuestion(Request $request)
    {
        $validated = $request->validate([
            'module_id' => 'nullable|exists:modules,id',
            'position' => 'nullable|integer|min:1',
            'passage_id' => 'nullable|exists:passages,id',
            'paired_passage_id' => 'nullable|exists:paired_passages,id',
            'question_number' => 'nullable|integer|min:1',
            'stem' => 'required|string',
            'question_type' => 'required|in:multiple_choice,student_produced_response',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'is_pretest' => 'boolean',
            'section_type' => 'nullable|in:reading_writing,math',
            'skill_domain' => 'nullable|string|max:255',
            'skill_subdomain' => 'nullable|string|max:255',
            'spr_hint' => 'nullable|string',
            'calculator_allowed' => 'boolean',
            'external_id' => 'nullable|string|max:255',
        ]);

        if (empty($validated['section_type']) && ! empty($validated['module_id'])) {
            $module = Module::with('section')->find($validated['module_id']);
            if ($module && $module->section) {
                $validated['section_type'] = $module->section->type;
            }
        }

        if (empty($validated['section_type'])) {
            return response()->json(['status' => 'error', 'message' => 'Section type is required.'], 422);
        }

        if ($validated['section_type'] === 'reading_writing' && $validated['question_type'] === 'student_produced_response') {
            return response()->json(['status' => 'error', 'message' => 'Reading & Writing does not support SPR.'], 422);
        }

        if ($validated['section_type'] === 'reading_writing' && ! empty($validated['passage_id'])) {
            $existing = Question::where('passage_id', $validated['passage_id'])->count();
            if ($existing > 0) {
                return response()->json(['status' => 'error', 'message' => 'One question per passage only for R&W.'], 422);
            }
        }

        if (empty($validated['difficulty']) || empty($validated['skill_domain'])) {
            $passageContent = !empty($validated['passage_id']) ? Passage::find($validated['passage_id'])?->content : null;
            $classification = $this->aiClassification->classify([
                'section_type' => $validated['section_type'],
                'stem' => $validated['stem'],
                'passage_content' => $passageContent,
            ]);
            $validated['difficulty'] = $validated['difficulty'] ?? $classification['difficulty'];
            $validated['skill_domain'] = $validated['skill_domain'] ?? $classification['skill_domain'];
        }

        $module_id = $validated['module_id'] ?? null;
        $position = $validated['position'];
        if ($module_id && empty($position)) {
            $max = (int) DB::table('module_questions')->where('module_id', $module_id)->max('position');
            $position = $max + 1;
        }

        unset($validated['module_id'], $validated['position'], $validated['question_number']);

        $question = Question::create($validated);
        if ($module_id) {
            // Auto-shift: if position taken, push existing ones forward
            DB::transaction(function () use ($module_id, $position) {
                DB::table('module_questions')
                    ->where('module_id', $module_id)
                    ->where('position', '>=', $position)
                    ->increment('position');
            });

            $module = Module::find($module_id);
            $module->questions()->attach($question->id, ['position' => $position]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Question created successfully (positions auto-shifted if needed).',
            'data' => $question,
        ], 201);
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
    public function bulkStoreQuestionsFromCsv(Request $request, BulkQuestionCsvImportService $csvImport)
    {
        $result = $csvImport->importFromRequest($request);
        return response()->json(['status' => 'success', 'message' => count($result['question_ids']).' question(s) created.', 'data' => $result], 201);
    }

    /**
     * Store answer choices for a question.
     */
    public function storeAnswerChoices(Request $request)
    {
        $validated = $request->validate([
            'question_id' => 'required|exists:questions,id',
            'choices' => 'required|array|min:1',
            'choices.*.label' => 'required|string|max:10',
            'choices.*.content' => 'required|string',
            'choices.*.is_correct' => 'boolean',
            'choices.*.order' => 'required|integer|min:1',
        ]);

        $createdChoices = [];
        foreach ($validated['choices'] as $choiceData) {
            $choice = AnswerChoice::create(array_merge($choiceData, ['question_id' => $validated['question_id']]));
            $createdChoices[] = $choice;
        }
        return response()->json(['status' => 'success', 'message' => 'Choices created.', 'data' => $createdChoices], 201);
    }

    /**
     * Store question explanation.
     */
    public function storeExplanation(Request $request)
    {
        $validated = $request->validate([
            'question_id' => 'required|exists:questions,id|unique:question_explanations,question_id',
            'explanation' => 'required|string',
            'rationale_a' => 'nullable|string',
            'rationale_b' => 'nullable|string',
            'rationale_c' => 'nullable|string',
            'rationale_d' => 'nullable|string',
            'strategy_tip' => 'nullable|string',
            'common_mistakes' => 'nullable|string',
        ]);
        $explanation = QuestionExplanation::create($validated);
        return response()->json(['status' => 'success', 'message' => 'Explanation created.', 'data' => $explanation], 201);
    }

    public function deleteTest($id)
    {
        Test::findOrFail($id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Test deleted.']);
    }

    public function deleteSection($id)
    {
        $section = Section::with('test')->findOrFail($id);
        $test = $section->test;
        $section->delete();
        if ($test) $test->refreshTotalDuration();
        return response()->json(['status' => 'success', 'message' => 'Section deleted.']);
    }

    public function deleteModule($id)
    {
        $module = Module::with('section.test')->findOrFail($id);
        $test = $module->section->test ?? null;
        $module->delete();
        if ($test) $test->refreshTotalDuration();
        return response()->json(['status' => 'success', 'message' => 'Module deleted.']);
    }

    public function deleteQuestion($id)
    {
        Question::findOrFail($id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Question deleted.']);
    }
}
