<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Test;

/**
 * Advisory (never blocking) checks on a test's difficulty distribution.
 *
 * Content quality is the author's responsibility; the platform only scores. But
 * because normal/Module-1 scoring is now IRT (difficulty-aware), a "standard" module
 * whose questions are almost all one difficulty distorts the ability estimate
 * (garbage-in, garbage-out). This surfaces a soft warning so the author can notice —
 * it does not stop publication.
 *
 * Intentionally-skewed adaptive Module 2 branches (difficulty_level easy/hard) are
 * excluded: their skew is by design and is already validated for separation by
 * TestStructureService::validateAdaptiveMeasurement().
 */
class DifficultyDistributionAdvisor
{
    /** A module below this many scored questions is too small to judge. */
    private const MIN_QUESTIONS = 5;

    /** Dominant-difficulty share at or above this fraction triggers a warning. */
    private const DOMINANT_THRESHOLD = 0.9;

    /**
     * @return array<int, array{section:string,module_number:int,message:string}>
     */
    public function warnings(Test $test): array
    {
        $test->loadMissing('sections.modules.questions');

        $warnings = [];
        foreach ($test->sections as $section) {
            foreach ($section->modules as $module) {
                // Only "standard" modules are expected to mix difficulties. Easy/hard
                // Module 2 branches are skewed by design.
                if ($module->difficulty_level !== Module::DIFFICULTY_STANDARD) {
                    continue;
                }

                $scored = $module->questions->where('is_pretest', false);
                $total = $scored->count();
                if ($total < self::MIN_QUESTIONS) {
                    continue;
                }

                $counts = $scored->countBy('difficulty');
                $dominantCount = $counts->max();
                $dominantFraction = $dominantCount / $total;
                if ($dominantFraction < self::DOMINANT_THRESHOLD) {
                    continue;
                }

                $dominant = $counts->sortDesc()->keys()->first();
                $warnings[] = [
                    'section' => (string) $section->name,
                    'module_number' => (int) $module->module_number,
                    'message' => sprintf(
                        '%s Module %d is %d%% "%s" questions. A standard module should mix easy/medium/hard; IRT scores may be distorted.',
                        $section->name,
                        (int) $module->module_number,
                        (int) round($dominantFraction * 100),
                        $dominant,
                    ),
                ];
            }
        }

        return $warnings;
    }
}
