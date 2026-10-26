<?php

namespace App\Services;

use App\Models\Test;
use RuntimeException;

class ScoreConversionService
{
    public function __construct(
        private FormScoringAuditService $audit,
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

        return $this->convertDefault($sectionType, $rawScore, $presentedQuestions);
    }

    /**
     * Look up scaled score from the default uncurved practice score table.
     * Absorbed from DefaultScoreConversionService. Used as fallback when no
     * approved set exists, and for legacy consensus rescoring.
     *
     * @return array{scaled_score:int,raw_score:int,conversion_set_id:null,conversion_version:string,estimate_kind:string,estimate_status:string}
     */
    public function convertDefault(string $sectionType, int $rawScore, int $presentedQuestions): array
    {
        $table = config("sat_scoring.normal_conversion.tables.{$sectionType}");
        $version = config('sat_scoring.normal_conversion.version');
        if (! is_array($table) || ! is_string($version) || $version === '') {
            throw new RuntimeException('Normal score conversion is not configured for this section.');
        }
        $expectedTotal = max(array_keys($table));
        if ($presentedQuestions !== $expectedTotal || ! array_key_exists($rawScore, $table)) {
            throw new RuntimeException("Normal conversion requires {$expectedTotal} presented questions and complete raw-score coverage.");
        }

        return [
            'scaled_score' => (int) $table[$rawScore],
            'raw_score' => $rawScore,
            'conversion_set_id' => null,
            'conversion_version' => $version,
            'estimate_kind' => 'normal_generic',
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
}
