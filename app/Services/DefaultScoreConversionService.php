<?php

namespace App\Services;

use RuntimeException;

class DefaultScoreConversionService
{
    public const ESTIMATE_KIND = 'normal_generic';

    /**
     * @return array{scaled_score:int,conversion_version:string,estimate_kind:string}
     */
    public function convert(string $sectionType, int $rawScore, int $presentedQuestions): array
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
            'conversion_version' => $version,
            'estimate_kind' => self::ESTIMATE_KIND,
        ];
    }
}
