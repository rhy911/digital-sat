<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Module;
use App\Models\Section;
use App\Services\BulkQuestionCsvImportService;
use App\Services\BulkQuestionImportService;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Http\Requests\Admin\AttachQuestionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuestionController extends Controller
{
    private const QUESTIONS_TABLE_PER_PAGE = 30;

    public function index(Request $request)
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

    private function formatQuestionSearchResults(\Illuminate\Support\Collection $rows): array
    {
        return $rows->map(fn (Question $row): array => [
            'value' => (string) $row->id,
            'text' => 'ID:'.$row->id.' - '.Str::limit(strip_tags((string) $row->stem), 50),
        ])->values()->all();
    }

    public function search(Request $request)
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

    public function show($id)
    {
        $question = Question::visibleTo(auth()->user())->with(['passage', 'answerChoices', 'explanation', 'sprCorrectAnswers'])->findOrFail($id);
        return response()->json([
            'status' => 'success',
            'data' => $question,
        ]);
    }

    public function update(UpdateQuestionRequest $request, $id)
    {
        $question = Question::with(['passage', 'answerChoices', 'explanation'])->findOrFail($id);

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

    public function attach(AttachQuestionRequest $request)
    {
        $validated = $request->validated();

        $module = Module::findOrFail($validated['module_id']);

        $question = Question::visibleTo(auth()->user())->findOrFail($validated['question_id']);

        $position = $validated['position'] ?? null;

        return DB::transaction(function () use ($module, $question, &$position) {
            if ($module->questions()->where('question_id', $question->id)->lockForUpdate()->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Question is already attached to this module.',
                ], 422);
            }

            if (empty($position)) {
                $max = (int) DB::table('module_questions')->where('module_id', $module->id)->max('position');
                $position = $max + 1;
            }

            DB::table('module_questions')
                ->where('module_id', $module->id)
                ->where('position', '>=', $position)
                ->increment('position');

            $module->questions()->attach($question->id, ['position' => $position]);

            return response()->json([
                'status' => 'success',
                'message' => 'Question attached to module successfully (positions auto-shifted if needed).',
            ]);
        });
    }

    public function bulkPreview(Request $request, BulkQuestionImportService $bulkQuestionImport)
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

    public function bulkStore(Request $request, BulkQuestionImportService $bulkQuestionImport)
    {
        $payload = $bulkQuestionImport->buildPayloadFromRequest($request);
        $result = $bulkQuestionImport->import($payload);
        return response()->json(['status' => 'success', 'message' => count($result['question_ids']).' question(s) created.', 'data' => $result], 201);
    }

    public function bulkPreviewCsv(Request $request, BulkQuestionCsvImportService $csvImport, BulkQuestionImportService $bulkQuestionImport)
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

    public function bulkStoreCsv(Request $request, BulkQuestionCsvImportService $csvImport, BulkQuestionImportService $bulkQuestionImport)
    {
        $payload = $csvImport->getPayloadFromRequest($request);
        $result = $bulkQuestionImport->import($payload);
        return response()->json(['status' => 'success', 'message' => count($result['question_ids']).' question(s) created.', 'data' => $result], 201);
    }

    public function bulkStoreZip(Request $request, BulkQuestionImportService $bulkQuestionImport)
    {
        try {
            $result = $bulkQuestionImport->importFromZip($request);
            return response()->json([
                'status' => 'success',
                'message' => count($result['question_ids']).' question(s) created.',
                'data' => $result
            ], 201);
        } catch (\Exception $e) {
            Log::error('ZIP Import Failed', ['exception' => $e]);
            return response()->json([
                'status' => 'error',
                'message' => 'ZIP Import Failed due to a server error. Please check the ZIP file format.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $question = Question::findOrFail($id);
        $this->authorize('delete', $question);

        $question->delete();
        return response()->json(['status' => 'success', 'message' => 'Question deleted.']);
    }
}
