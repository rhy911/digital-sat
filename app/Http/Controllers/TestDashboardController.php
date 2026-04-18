<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Models\Section;
use App\Models\Module;
use App\Models\Passage;
use App\Models\Question;
use App\Models\AnswerChoice;
use App\Models\QuestionExplanation;
use Illuminate\Http\Request;

class TestDashboardController extends Controller
{
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
            $questions = Question::with(['passage', 'answerChoices', 'explanation'])->latest()->get();
        } catch (\Exception $e) {
            $questions = collect();
        }

        return view('test-dashboard', compact('tests', 'passages', 'questions'));
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

        // Default total_duration_minutes to 0; will be updated as modules are added
        $validated['total_duration_minutes'] = 0;

        $test = Test::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Test created successfully',
            'data' => $test
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
            'data' => $test
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
            'order' => 'required|integer|min:1',
        ]);

        // Auto-generate name if not provided
        if (empty($validated['name'])) {
            $validated['name'] = $validated['type'] === 'reading_writing' ? 'Reading and Writing' : 'Math';
        }

        $section = Section::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Section created successfully',
            'data' => $section
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
            'order' => 'required|integer|min:1',
        ]);

        $module = Module::create($validated);
        
        // Update Test total duration
        if ($module->section && $module->section->test) {
            $module->section->test->refreshTotalDuration();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Module created successfully',
            'data' => $module
        ], 201);
    }

    /**
     * Store a new passage.
     */
    public function storePassage(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'passage_type' => 'required|in:single,paired',
            'word_count' => 'nullable|integer|min:0',
            'source_title' => 'nullable|string|max:255',
            'source_author' => 'nullable|string|max:255',
            'source_year' => 'nullable|integer',
            'genre' => 'required|in:literary_narrative,social_science,natural_science,humanities',
        ]);

        $passage = Passage::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Passage created successfully',
            'data' => $passage
        ], 201);
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
            'question_number' => 'required|integer|min:1',
            'stem' => 'required|string',
            'question_type' => 'required|in:multiple_choice,student_produced_response',
            'difficulty' => 'required|in:easy,medium,hard',
            'is_pretest' => 'boolean',
            'section_type' => 'nullable|in:reading_writing,math',
            'skill_domain' => 'required|string|max:255',
            'skill_subdomain' => 'nullable|string|max:255',
            'spr_hint' => 'nullable|string',
            'calculator_allowed' => 'boolean',
            'external_id' => 'nullable|string|max:255',
        ]);

        // Auto-fetch section type from module if provided
        if (empty($validated['section_type']) && !empty($validated['module_id'])) {
            $module = Module::with('section')->find($validated['module_id']);
            if ($module && $module->section) {
                $validated['section_type'] = $module->section->type;
            }
        }

        if (empty($validated['section_type'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Section type is required (could not auto-detect from module).'
            ], 422);
        }

        // Recommendation 4: Enforce 1:1 Passage ratio for Reading & Writing
        if ($validated['section_type'] === 'reading_writing' && !empty($validated['passage_id'])) {
            $existingQuestionCount = Question::where('passage_id', $validated['passage_id'])->count();
            if ($existingQuestionCount > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Digital SAT Reading & Writing must have exactly one question per passage.'
                ], 422);
            }
        }

        $module_id = $validated['module_id'] ?? null;
        $position = $validated['position'] ?? 1;
        unset($validated['module_id'], $validated['position']);

        $question = Question::create($validated);

        // Link to module if provided
        if ($module_id) {
            $module = Module::find($module_id);
            $module->questions()->attach($question->id, ['position' => $position]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Question created successfully',
            'data' => $question
        ], 201);
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
            $choice = AnswerChoice::create(array_merge($choiceData, [
                'question_id' => $validated['question_id'],
            ]));
            $createdChoices[] = $choice;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Answer choices created successfully',
            'data' => $createdChoices
        ], 201);
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

        return response()->json([
            'status' => 'success',
            'message' => 'Explanation created successfully',
            'data' => $explanation
        ], 201);
    }

    /**
     * Delete a test.
     */
    public function deleteTest($id)
    {
        $test = Test::findOrFail($id);
        $test->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Test deleted successfully'
        ]);
    }

    /**
     * Delete a section.
     */
    public function deleteSection($id)
    {
        $section = Section::with('test')->findOrFail($id);
        $test = $section->test;
        $section->delete();

        if ($test) {
            $test->refreshTotalDuration();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Section deleted successfully'
        ]);
    }

    /**
     * Delete a module.
     */
    public function deleteModule($id)
    {
        $module = Module::with('section.test')->findOrFail($id);
        $test = $module->section->test ?? null;
        $module->delete();

        if ($test) {
            $test->refreshTotalDuration();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Module deleted successfully'
        ]);
    }

    /**
     * Delete a question.
     */
    public function deleteQuestion($id)
    {
        $question = Question::findOrFail($id);
        $question->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Question deleted successfully'
        ]);
    }
}
