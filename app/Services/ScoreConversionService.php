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
    public function convert(Test $test, string $sectionType, int $rawScore, int $presentedQuestions, string $m2Difficulty = 'standard'): array
    {
        $approved = $this->tryApprovedConversion($test, $sectionType, $m2Difficulty, $rawScore);
        if ($approved) {
            return $approved;
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

    /**
     * Resolve a scaled score from the test's approved, checksum-valid conversion set
     * for the given section and Module 2 path ('standard' for normal, 'easy'/'hard'
     * for adaptive). Returns null when no such set/row applies, so adaptive scoring can
     * fall back to the IRT curve rather than the raw-count generic table.
     *
     * @return array{scaled_score:int,raw_score:int,conversion_set_id:int,conversion_version:string,estimate_kind:string,estimate_status:string}|null
     */
    public function tryApprovedConversion(Test $test, string $sectionType, string $m2Difficulty, int $rawScore): ?array
    {
        $set = $test->approvedScoreConversionSet()->with('rows')->first();
        if (! $set || ! hash_equals((string) $set->form_checksum, $this->audit->formChecksum($test))) {
            return null;
        }

        $row = $set->rows->first(fn ($candidate) => $candidate->section_type === $sectionType
            && $candidate->m2_difficulty === $m2Difficulty
            && (int) $candidate->raw_score === $rawScore);
        if (! $row) {
            return null;
        }

        return [
            'scaled_score' => (int) $row->scaled_score,
            'raw_score' => $rawScore,
            'conversion_set_id' => (int) $set->id,
            'conversion_version' => 'form_v'.$set->version,
            'estimate_kind' => 'normal_form_specific',
            'estimate_status' => 'estimated_practice_score',
        ];
    }

    public function approvedSet(Test $test): ?ScoreConversionSet
    {
        return $test->approvedScoreConversionSet()->first();
    }
}
