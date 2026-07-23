<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Section;
use RuntimeException;

class AdaptiveScoreConversionService
{
    public const ESTIMATE_KIND = 'adaptive_irt_provisional';

    /**
     * Convert a section ability estimate to a scaled score through the path-aware,
     * section-specific curve. The lower/upper band is the same curve applied to
     * theta ± SE, so the band is always centred on the reported score.
     *
     * @return array{scaled_score:int,lower:int,upper:int,scaled_se:int,conversion_version:string,estimate_kind:string}
     */
    public function convert(float $theta, float $standardError, string $sectionType, ?string $path = null): array
    {
        $path = $path === Module::DIFFICULTY_EASY ? Module::DIFFICULTY_EASY : Module::DIFFICULTY_HARD;
        $se = max(0.0, $standardError);

        $score = $this->mapTheta($theta, $sectionType, $path);
        $lower = $this->mapTheta($theta - $se, $sectionType, $path);
        $upper = $this->mapTheta($theta + $se, $sectionType, $path);
        $scaledSe = $this->roundTen($se * (float) config('sat_scoring.adaptive_conversion.points_per_theta', 100));

        return [
            'scaled_score' => $score,
            'lower' => min($score, $lower),
            'upper' => max($score, $upper),
            'scaled_se' => $scaledSe,
            'conversion_version' => (string) config('sat_scoring.adaptive_conversion.version'),
            'estimate_kind' => self::ESTIMATE_KIND,
        ];
    }

    public function totalRange(array $readingWriting, array $math): array
    {
        $total = $readingWriting['scaled_score'] + $math['scaled_score'];
        $combinedSe = sqrt(($readingWriting['scaled_se'] ** 2) + ($math['scaled_se'] ** 2));
        $margin = $this->roundTen($combinedSe);

        return [
            'score' => $total,
            'lower' => max(400, $total - $margin),
            'upper' => min(1600, $total + $margin),
        ];
    }

    private function mapTheta(float $theta, string $sectionType, string $path): int
    {
        $scaled = $this->interpolate($this->curve($sectionType, $path), $theta);

        return max(
            (int) config('sat_scoring.adaptive_conversion.minimum', 200),
            min((int) config('sat_scoring.adaptive_conversion.maximum', 800), $this->roundTen($scaled)),
        );
    }

    /**
     * @return array<int, array{0: float|int, 1: float|int}> ascending [theta, scaled] anchors
     */
    private function curve(string $sectionType, string $path): array
    {
        $curves = config('sat_scoring.adaptive_conversion.curves');
        $sectionKey = $sectionType === Section::TYPE_MATH ? 'math' : 'reading_writing';
        $curve = $curves[$sectionKey][$path] ?? $curves[$sectionKey][Module::DIFFICULTY_HARD] ?? null;
        if (! is_array($curve) || count($curve) < 2) {
            throw new RuntimeException('Adaptive conversion curve is not configured for this section/path.');
        }

        return array_values($curve);
    }

    /**
     * Piecewise-linear interpolation over ascending [theta, scaled] anchors,
     * clamped to the endpoints outside the anchor range.
     *
     * @param  array<int, array{0: float|int, 1: float|int}>  $curve
     */
    private function interpolate(array $curve, float $theta): float
    {
        $first = $curve[0];
        $last = $curve[count($curve) - 1];
        if ($theta <= (float) $first[0]) {
            return (float) $first[1];
        }
        if ($theta >= (float) $last[0]) {
            return (float) $last[1];
        }

        for ($i = 0; $i < count($curve) - 1; $i++) {
            $t0 = (float) $curve[$i][0];
            $t1 = (float) $curve[$i + 1][0];
            if ($theta >= $t0 && $theta <= $t1) {
                $s0 = (float) $curve[$i][1];
                $s1 = (float) $curve[$i + 1][1];
                $fraction = ($t1 - $t0) > 0.0 ? ($theta - $t0) / ($t1 - $t0) : 0.0;

                return $s0 + $fraction * ($s1 - $s0);
            }
        }

        return (float) $last[1];
    }

    private function roundTen(float $value): int
    {
        return (int) (round($value / 10) * 10);
    }
}
