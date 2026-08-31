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
 * Adaptive Module 2 branches (difficulty_level easy/hard) are also checked for
 * mean difficulty separation: if the gap between hard and easy mean difficulty is
 * below 0.5, a soft warning is surfaced so the author can improve separation without
 * blocking test day publication.
 */
class DifficultyDistributionAdvisor
{
    /** A module below this many scored questions is too small to judge. */
    private const MIN_QUESTIONS = 5;

    /** Dominant-difficulty share at or above this fraction triggers a warning. */
    private const DOMINANT_THRESHOLD = 0.9;

    /** Minimum recommended mean IRT difficulty separation between Hard and Easy M2 branches. */
    private const MIN_ADAPTIVE_SEPARATION = 0.5;

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

            if ($test->test_type === Test::TYPE_ADAPTIVE_FULL) {
                $easy = $section->modules->first(fn ($module) => (int) $module->module_number === 2 && $module->difficulty_level === Module::DIFFICULTY_EASY);
                $hard = $section->modules->first(fn ($module) => (int) $module->module_number === 2 && $module->difficulty_level === Module::DIFFICULTY_HARD);
                if ($easy && $hard) {
                    $easyMean = $easy->questions->where('is_pretest', false)->avg('irt_b');
                    $hardMean = $hard->questions->where('is_pretest', false)->avg('irt_b');
                    if ($easyMean !== null && $hardMean !== null) {
                        $diff = (float) $hardMean - (float) $easyMean;
                        if ($diff < self::MIN_ADAPTIVE_SEPARATION) {
                            $warnings[] = [
                                'section' => (string) $section->name,
                                'module_number' => 2,
                                'message' => sprintf(
                                    '%s Module 2 hard/easy branches have mean IRT difficulty separation of %.2f (< %.2f). Adaptive ability estimation may be less discriminating.',
                                    $section->name,
                                    $diff,
                                    self::MIN_ADAPTIVE_SEPARATION,
                                ),
                            ];
                        }
                    }
                }
            }
        }

        return $warnings;
    }
}
