<?php

namespace App\Support;

class QuestionPacing
{
    public const RUSHED_THRESHOLD = 0.40;
    public const SLOW_THRESHOLD = 1.50;

    /**
     * Fallback expected-time (seconds) for a question with no expected_time set,
     * from config/sat_scoring.php pacing_defaults (see comment there for source).
     */
    public static function expectedSeconds(?string $sectionType, ?string $difficulty): int
    {
        $section = $sectionType === 'math' ? 'math' : 'reading_writing';
        $defaults = config("sat_scoring.pacing_defaults.{$section}");
        $key = strtolower($difficulty ?? 'unknown');

        return (int) ($defaults[$key] ?? $defaults['unknown']);
    }

    /**
     * Centralized pacing interpretation for an answer.
     *
     * Rules:
     * - zero or missing timing -> 'No timing data'
     * - positive timing with valid expected time:
     *     - ratio < 0.40 -> potentially rushed
     *     - ratio > 1.50 -> possibly stuck/slow
     *     - 0.40 <= ratio <= 1.50 -> expected range
     *
     * @return array{
     *     status: 'none'|'rushed'|'expected'|'slow',
     *     label: string,
     *     shortLabel: string,
     *     actualSeconds: ?int,
     *     expectedSeconds: ?int,
     *     isEstimatedExpected: bool,
     *     ratio: ?float
     * }
     */
    public static function interpret(?int $timeSpent, ?int $expectedTime, bool $isEstimatedExpected = false): array
    {
        if ($timeSpent === null || $timeSpent <= 0) {
            return [
                'status' => 'none',
                'label' => 'No timing data',
                'shortLabel' => 'No timing data',
                'actualSeconds' => null,
                'expectedSeconds' => $expectedTime,
                'isEstimatedExpected' => $isEstimatedExpected,
                'ratio' => null,
            ];
        }

        if ($expectedTime === null || $expectedTime <= 0) {
            return [
                'status' => 'expected',
                'label' => 'Recorded time (' . $timeSpent . 's)',
                'shortLabel' => $timeSpent . 's',
                'actualSeconds' => $timeSpent,
                'expectedSeconds' => null,
                'isEstimatedExpected' => $isEstimatedExpected,
                'ratio' => null,
            ];
        }

        $ratio = $timeSpent / $expectedTime;

        if ($ratio < self::RUSHED_THRESHOLD) {
            return [
                'status' => 'rushed',
                'label' => 'Potentially rushed (<40% expected)',
                'shortLabel' => 'Rushed',
                'actualSeconds' => $timeSpent,
                'expectedSeconds' => $expectedTime,
                'isEstimatedExpected' => $isEstimatedExpected,
                'ratio' => round($ratio, 2),
            ];
        }

        if ($ratio > self::SLOW_THRESHOLD) {
            return [
                'status' => 'slow',
                'label' => 'Possibly stuck/slow (>150% expected)',
                'shortLabel' => 'Stuck / Slow',
                'actualSeconds' => $timeSpent,
                'expectedSeconds' => $expectedTime,
                'isEstimatedExpected' => $isEstimatedExpected,
                'ratio' => round($ratio, 2),
            ];
        }

        return [
            'status' => 'expected',
            'label' => 'Expected pace (40–150%)',
            'shortLabel' => 'Expected pace',
            'actualSeconds' => $timeSpent,
            'expectedSeconds' => $expectedTime,
            'isEstimatedExpected' => $isEstimatedExpected,
            'ratio' => round($ratio, 2),
        ];
    }
}
