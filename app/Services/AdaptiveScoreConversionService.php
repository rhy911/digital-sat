<?php

namespace App\Services;

class AdaptiveScoreConversionService
{
    public const ESTIMATE_KIND = 'adaptive_irt_provisional';

    /**
     * @return array{scaled_score:int,lower:int,upper:int,scaled_se:int,conversion_version:string,estimate_kind:string}
     */
    public function convert(float $theta, float $standardError): array
    {
        $score = $this->mapTheta($theta);
        $lower = $this->mapTheta($theta - max(0, $standardError));
        $upper = $this->mapTheta($theta + max(0, $standardError));
        $scaledSe = $this->roundTen(max(0, $standardError) * (float) config('sat_scoring.adaptive_conversion.points_per_theta', 100));

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

    private function mapTheta(float $theta): int
    {
        $score = (float) config('sat_scoring.adaptive_conversion.center', 500)
            + ((float) config('sat_scoring.adaptive_conversion.points_per_theta', 100) * $theta);

        return max(
            (int) config('sat_scoring.adaptive_conversion.minimum', 200),
            min((int) config('sat_scoring.adaptive_conversion.maximum', 800), $this->roundTen($score)),
        );
    }

    private function roundTen(float $value): int
    {
        return (int) (round($value / 10) * 10);
    }
}
