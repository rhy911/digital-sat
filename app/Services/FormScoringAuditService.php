<?php

namespace App\Services;

use App\Models\Module;
use App\Models\ScoreConversionSet;
use App\Models\Section;
use App\Models\Test;
use Illuminate\Validation\ValidationException;

class FormScoringAuditService
{
    public function __construct(private TestStructureService $structures) {}

    /**
     * @return array{eligible:bool,errors:array<int,string>,warnings:array<int,string>,form_checksum:string}
     */
    public function audit(Test $test, ?ScoreConversionSet $set = null): array
    {
        $errors = [];
        $warnings = [];
        $test->load('sections.modules.questions');

        if ($test->test_type !== Test::TYPE_FULL) {
            $errors[] = 'Only Normal Full tests accept raw score conversion tables.';
        }

        try {
            $this->structures->validate($test);
        } catch (ValidationException $exception) {
            $errors[] = collect($exception->errors())->flatten()->first() ?? 'Test structure is invalid.';
        }

        foreach ($test->sections as $section) {
            $expectedCount = $section->type === Section::TYPE_RW ? Module::RW_QUESTIONS : Module::MATH_QUESTIONS;
            $expectedDuration = $section->type === Section::TYPE_RW ? Module::RW_DURATION : Module::MATH_DURATION;
            $seen = [];

            foreach ($section->modules as $module) {
                if ($module->questions->count() !== $expectedCount || (int) $module->total_questions !== $expectedCount) {
                    $errors[] = "{$section->name}, {$this->moduleLabel($module)} requires exactly {$expectedCount} questions.";
                }
                if ((int) $module->duration_minutes !== $expectedDuration) {
                    $errors[] = "{$section->name}, {$this->moduleLabel($module)} requires {$expectedDuration} minutes.";
                }
                foreach ($module->questions as $question) {
                    if (isset($seen[$question->id])) {
                        $errors[] = "{$section->name} repeats question {$question->id} across modules.";
                    }
                    $seen[$question->id] = true;
                }
            }
        }

        if ($set) {
            $errors = array_merge($errors, $this->conversionErrors($test, $set));
        } else {
            $warnings[] = 'No conversion set supplied; conversion coverage was not checked.';
        }

        return [
            'eligible' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'warnings' => $warnings,
            'form_checksum' => $this->formChecksum($test),
        ];
    }

    public function formChecksum(Test $test): string
    {
        $test->loadMissing('sections.modules.questions');
        $payload = $test->sections->sortBy('order')->map(fn ($section) => [
            'type' => $section->type,
            'order' => (int) $section->order,
            'modules' => $section->modules->sortBy([['module_number', 'asc'], ['difficulty_level', 'asc']])->map(fn ($module) => [
                'number' => (int) $module->module_number,
                'difficulty' => $module->difficulty_level,
                'duration' => (int) $module->duration_minutes,
                'questions' => $module->questions->sortBy('pivot.position')->map(fn ($question) => [
                    'id' => (int) $question->id,
                    'pretest' => (bool) $question->is_pretest,
                    'a' => (float) $question->irt_a,
                    'b' => (float) $question->irt_b,
                    'c' => (float) $question->irt_c,
                ])->values()->all(),
            ])->values()->all(),
        ])->values()->all();

        return hash('sha256', json_encode($payload, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }

    private function conversionErrors(Test $test, ScoreConversionSet $set): array
    {
        $errors = [];
        $set->loadMissing('rows');
        if ((int) $set->test_id !== (int) $test->id) {
            return ['Conversion set does not belong to this test.'];
        }

        foreach ($test->sections as $section) {
            $m1 = $section->modules->first(fn ($module) => (int) $module->module_number === 1);
            $m2 = $section->modules->first(fn ($module) => (int) $module->module_number === 2);
            if ($m1 && $m2) {
                $scoredTotal = $m1->questions->count() + $m2->questions->count();
                $rows = $set->rows
                    ->where('section_type', $section->type)
                    ->where('m2_difficulty', 'standard')
                    ->sortBy('raw_score')
                    ->values();
                if ($rows->pluck('raw_score')->map(fn ($score) => (int) $score)->all() !== range(0, $scoredTotal)) {
                    $errors[] = "{$section->name} conversion must cover raw scores 0 through {$scoredTotal}.";

                    continue;
                }
                $previous = null;
                foreach ($rows as $row) {
                    $scaled = (int) $row->scaled_score;
                    if ($scaled < 200 || $scaled > 800 || $scaled % 10 !== 0 || ($previous !== null && $scaled < $previous)) {
                        $errors[] = "{$section->name} conversion must be monotonic, 200-800, in 10-point increments.";
                        break;
                    }
                    $previous = $scaled;
                }
            }
        }

        return $errors;
    }

    private function validParameters(object $question): bool
    {
        return is_numeric($question->irt_a) && is_numeric($question->irt_b) && is_numeric($question->irt_c)
            && (float) $question->irt_a > 0.0 && (float) $question->irt_a <= 4.0
            && (float) $question->irt_b >= -6.0 && (float) $question->irt_b <= 6.0
            && (float) $question->irt_c >= 0.0 && (float) $question->irt_c < 1.0;
    }

    private function moduleLabel(Module $module): string
    {
        return 'Module '.(int) $module->module_number.' '.ucfirst($module->difficulty_level);
    }
}
