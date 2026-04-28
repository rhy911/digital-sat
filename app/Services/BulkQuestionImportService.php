<?php

namespace App\Services;

use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Passage;
use App\Models\Question;
use App\Models\QuestionExplanation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BulkQuestionImportService
{
    public function __construct(
        private AiClassificationService $aiClassification
    ) {}

    /**
     * Build the bulk-import payload from JSON body, multipart JSON file, or merged form fields.
     */
    public function buildPayloadFromRequest(Request $request): array
    {
        if ($request->hasFile('json_file')) {
            $request->validate(['json_file' => 'required|file|max:5120']);
            $file = $request->file('json_file');
            $raw = (string) file_get_contents($file->getRealPath());
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                throw ValidationException::withMessages(['json_file' => ['Invalid JSON.']]);
            }
            $payload = $decoded;
        } else {
            $payload = $request->all();
        }

        foreach (['module_id', 'start_position'] as $key) {
            if ($request->filled($key)) $payload[$key] = $request->input($key);
        }

        if (array_is_list($payload) && isset($payload[0]['stem'])) {
            $payload = ['items' => $payload];
        }

        return $payload;
    }

    /**
     * Validate and create questions.
     */
    public function import(array $payload): array
    {
        $validated = $this->validate($payload);
        $module = Module::with('section')->findOrFail($validated['module_id']);
        $sectionType = $module->section?->type;

        $createdIds = [];
        $passagesCreated = 0;

        DB::transaction(function () use ($validated, $module, $sectionType, &$createdIds, &$passagesCreated) {
            $startPos = (int) ($validated['start_position'] ?? 1);
            $itemCount = count($validated['items']);

            // Auto-shift: move everything after startPos forward by itemCount
            DB::table('module_questions')
                ->where('module_id', $module->id)
                ->where('position', '>=', $startPos)
                ->increment('position', $itemCount);

            $position = $startPos;
            foreach ($validated['items'] as $index => $item) {
                $passageId = $item['passage_id'] ?? null;
                $fromInline = false;
                $inline = $item['passage'] ?? null;
                if (is_array($inline) && trim((string) ($inline['content'] ?? '')) !== '') {
                    $passage = $this->createPassageFromBulkArray($inline);
                    $passageId = $passage->id;
                    $fromInline = true;
                    $passagesCreated++;
                }

                if ($sectionType === 'reading_writing' && empty($passageId)) {
                    throw ValidationException::withMessages(["items.$index.passage" => ['Reading & Writing requires a passage.']]);
                }

                $questionAttrs = [
                    'passage_id' => $passageId,
                    'paired_passage_id' => $item['paired_passage_id'] ?? null,
                    'stem' => $item['stem'],
                    'question_type' => $item['question_type'],
                    'difficulty' => $item['difficulty'],
                    'is_pretest' => (bool) ($item['is_pretest'] ?? false),
                    'section_type' => $sectionType,
                    'skill_domain' => $item['skill_domain'],
                    'skill_subdomain' => $item['skill_subdomain'] ?? null,
                    'spr_hint' => $item['spr_hint'] ?? null,
                    'calculator_allowed' => (bool) ($item['calculator_allowed'] ?? true),
                    'external_id' => $item['external_id'] ?? null,
                ];

                $question = Question::create($questionAttrs);
                $module->questions()->attach($question->id, ['position' => $position]);

                if ($item['question_type'] === 'multiple_choice') {
                    foreach ($item['choices'] as $ord => $choiceData) {
                        AnswerChoice::create([
                            'question_id' => $question->id,
                            'label' => $choiceData['label'],
                            'content' => $choiceData['content'],
                            'is_correct' => (bool) ($choiceData['is_correct'] ?? false),
                            'order' => (int) ($choiceData['order'] ?? ($ord + 1)),
                        ]);
                    }
                } else {
                    foreach ($item['spr_correct_answers'] as $answerText) {
                        DB::table('spr_correct_answers')->insert([
                            'question_id' => $question->id,
                            'answer' => $answerText,
                            'answer_type' => 'exact',
                            'created_at' => now(),
                        ]);
                    }
                }

                if (! empty($item['explanation'])) {
                    QuestionExplanation::create([
                        'question_id' => $question->id,
                        'explanation' => $item['explanation'],
                        'rationale_a' => $item['rationale_a'] ?? null,
                        'rationale_b' => $item['rationale_b'] ?? null,
                        'rationale_c' => $item['rationale_c'] ?? null,
                        'rationale_d' => $item['rationale_d'] ?? null,
                    ]);
                }

                $createdIds[] = $question->id;
                $position++;
            }
        });

        return ['question_ids' => $createdIds, 'passages_created' => $passagesCreated];
    }

    /**
     * Validate the bulk payload.
     */
    public function validate(array $payload): array
    {
        $payload = $this->normalizePassageStringsInItems($payload);
        $validated = Validator::make($payload, $this->bulkItemValidationRules())->validate();

        $module = Module::with('section')->findOrFail($validated['module_id']);
        $sectionType = $module->section?->type;

        foreach ($validated['items'] as $index => &$item) {
            $item['section_type'] = $sectionType;
            $path = 'items.'.$index;

            if (empty($item['difficulty']) || empty($item['skill_domain'])) {
                $classification = $this->aiClassification->classify([
                    'section_type' => $sectionType,
                    'stem' => $item['stem'],
                    'passage_content' => $item['passage']['content'] ?? null,
                ]);
                $item['difficulty'] = $item['difficulty'] ?? $classification['difficulty'];
                $item['skill_domain'] = $item['skill_domain'] ?? $classification['skill_domain'];
                if ($sectionType === 'reading_writing' && isset($item['passage']) && empty($item['passage']['genre'])) {
                    $item['passage']['genre'] = $classification['genre'];
                }
            }

            if ($sectionType === 'reading_writing') {
                if ($item['question_type'] === 'student_produced_response') {
                    throw ValidationException::withMessages([$path.'.question_type' => ['Reading & Writing no SPR.']]);
                }
            }
        }

        return $validated;
    }

    private function normalizePassageStringsInItems(array $payload): array
    {
        if (! isset($payload['items'])) return $payload;
        foreach ($payload['items'] as $i => $row) {
            if (isset($row['passage']) && is_string($row['passage'])) {
                $payload['items'][$i]['passage'] = ['content' => $row['passage']];
            }
        }
        return $payload;
    }

    private function bulkItemValidationRules(): array
    {
        return [
            'module_id' => 'required|exists:modules,id',
            'start_position' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.stem' => 'required|string',
            'items.*.question_type' => 'required|in:multiple_choice,student_produced_response',
            'items.*.difficulty' => 'nullable|in:easy,medium,hard',
            'items.*.skill_domain' => 'nullable|string',
            'items.*.passage_id' => 'nullable|exists:passages,id',
            'items.*.passage' => 'nullable|array',
            'items.*.choices' => 'nullable|array',
            'items.*.spr_correct_answers' => 'nullable|array',
        ];
    }

    private function createPassageFromBulkArray(array $passage): Passage
    {
        return Passage::create([
            'content' => $passage['content'],
            'passage_type' => $passage['passage_type'] ?? 'single',
            'genre' => $passage['genre'] ?? 'humanities',
        ]);
    }
}
