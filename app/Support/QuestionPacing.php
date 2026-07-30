<?php

namespace App\Support;

class QuestionPacing
{
    /**
     * Fallback expected-time (seconds) for a question with no expected_time set,
     * from config/sat_scoring.php pacing_defaults (see comment there for source).
     */
    public static function expectedSeconds(?string $sectionType, ?string $difficulty): int
    {
        $section = $sectionType === 'math' ? 'math' : 'reading_writing';
        $defaults = config("sat_scoring.pacing_defaults.{$section}");
        $key = strtolower($difficulty ?? 'unknown');

        return $defaults[$key] ?? $defaults['unknown'];
    }
}
