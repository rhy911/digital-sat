<?php

namespace App\Services;

use App\Models\ScoreConversionSet;
use App\Models\Test;

class ScoreConversionService
{
    public function __construct(
        private FormScoringAuditService $audit,
        private DefaultScoreConversionService $defaults,
    ) {}

    /**
     * @return array{scaled_score:int,raw_score:int,conversion_set_id:?int,conversion_version:string,estimate_kind:string,estimate_status:string}
     */
    public function convert(Test $test, string $sectionType, int $rawScore, int $presentedQuestions): array
    {
        $set = $test->approvedScoreConversionSet()->with('rows')->first();
        if ($set && hash_equals((string) $set->form_checksum, $this->audit->formChecksum($test))) {
            $row = $set->rows->first(fn ($candidate) => $candidate->section_type === $sectionType
                && $candidate->m2_difficulty === 'standard'
                && (int) $candidate->raw_score === $rawScore);
            if ($row) {
                return [
                    'scaled_score' => (int) $row->scaled_score,
                    'raw_score' => $rawScore,
                    'conversion_set_id' => (int) $set->id,
                    'conversion_version' => 'form_v'.$set->version,
                    'estimate_kind' => 'normal_form_specific',
                    'estimate_status' => 'estimated_practice_score',
                ];
            }
        }

        $fallback = $this->defaults->convert($sectionType, $rawScore, $presentedQuestions);

        return [
            'scaled_score' => $fallback['scaled_score'],
            'raw_score' => $rawScore,
            'conversion_set_id' => null,
            'conversion_version' => $fallback['conversion_version'],
            'estimate_kind' => $fallback['estimate_kind'],
            'estimate_status' => 'estimated_practice_score',
        ];
    }

    public function approvedSet(Test $test): ?ScoreConversionSet
    {
        return $test->approvedScoreConversionSet()->first();
    }
}
