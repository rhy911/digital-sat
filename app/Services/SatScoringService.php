<?php

namespace App\Services;

use App\Models\Module;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class SatScoringService
{
    private const GRID_MIN = -4.0;

    private const GRID_MAX = 4.0;

    private const GRID_STEP = 0.05;

    /**
     * Score section responses on the internal ability scale.
     * Scaled reporting belongs to ScoreConversionService.
     *
     * @return array{raw_score:int,scored_questions:int,theta:float,theta_se:float,module2_path:string,method:string}
     */
    public function scoreSection(
        Collection $module1Responses,
        Collection $module2Responses,
        ?string $m2Path = null
    ): array {
        $m1 = $this->scoredResponses($module1Responses);
        $m2 = $this->scoredResponses($module2Responses);
        if ($m1->isEmpty() || $m2->isEmpty()) {
            throw new InvalidArgumentException('Both adaptive modules require scored responses.');
        }

        $module1Ability = $this->estimateAbility($m1);
        $path = $m2Path ?? $this->routeModule2($module1Ability['theta']);
        $allResponses = $m1->concat($m2)->values();
        $ability = $this->estimateAbility($allResponses);

        return [
            'raw_score' => $allResponses->where('is_correct', true)->count(),
            'scored_questions' => $allResponses->count(),
            'theta' => round($ability['theta'], 3),
            'theta_se' => round($ability['se'], 3),
            'module2_path' => $path,
            'method' => $ability['method'],
        ];
    }

    /**
     * Compatibility helper for callers that only need theta.
     */
    public function estimateTheta(Collection $responses): float
    {
        return $this->estimateAbility($responses)['theta'];
    }

    /**
     * Estimate ability with a standard-normal-prior EAP over a fixed theta grid.
     *
     * @return array{theta:float,se:float,method:string,item_count:int}
     */
    public function estimateAbility(Collection $responses): array
    {
        $responses = $this->scoredResponses($responses)->values();
        if ($responses->isEmpty()) {
            throw new InvalidArgumentException('Ability estimation requires at least one scored response.');
        }

        $logPosteriors = [];
        for ($theta = self::GRID_MIN; $theta <= self::GRID_MAX + 1e-9; $theta += self::GRID_STEP) {
            $logPosterior = -0.5 * ($theta ** 2);
            foreach ($responses as $response) {
                [$a, $b, $c] = $this->itemParameters($response);
                $probability = $c + (1 - $c) / (1 + exp(-$a * ($theta - $b)));
                $probability = max(1e-12, min(1 - 1e-12, $probability));
                $logPosterior += $response->is_correct
                    ? log($probability)
                    : log(1 - $probability);
            }

            $logPosteriors[] = ['theta' => $theta, 'log_weight' => $logPosterior];
        }

        $maxLogWeight = max(array_column($logPosteriors, 'log_weight'));
        $weightSum = 0.0;
        $weightedTheta = 0.0;
        foreach ($logPosteriors as &$point) {
            $point['weight'] = exp($point['log_weight'] - $maxLogWeight);
            $weightSum += $point['weight'];
            $weightedTheta += $point['theta'] * $point['weight'];
        }
        unset($point);

        if (! is_finite($weightSum) || $weightSum <= 0.0) {
            throw new InvalidArgumentException('Ability estimation produced an invalid posterior.');
        }

        $mean = $weightedTheta / $weightSum;
        $variance = 0.0;
        foreach ($logPosteriors as $point) {
            $variance += (($point['theta'] - $mean) ** 2) * $point['weight'];
        }
        $variance /= $weightSum;

        return [
            'theta' => round($mean, 6),
            'se' => round(sqrt(max(0.0, $variance)), 6),
            'method' => 'eap_3pl_v1',
            'item_count' => $responses->count(),
        ];
    }

    public function routeModule2(float $thetaM1): string
    {
        return $thetaM1 >= 0.0 ? Module::DIFFICULTY_HARD : Module::DIFFICULTY_EASY;
    }

    private function scoredResponses(Collection $responses): Collection
    {
        return $responses->filter(fn ($response) => ! (bool) $response->question?->is_pretest);
    }

    /**
     * @return array{float,float,float}
     */
    private function itemParameters(object $response): array
    {
        $question = $response->question ?? null;
        if (! $question || ! is_numeric($question->irt_a) || ! is_numeric($question->irt_b) || ! is_numeric($question->irt_c)) {
            throw new InvalidArgumentException('Scored questions require explicit IRT parameters.');
        }

        $a = (float) $question->irt_a;
        $b = (float) $question->irt_b;
        $c = (float) $question->irt_c;
        if ($a <= 0.0 || $a > 4.0 || $b < -6.0 || $b > 6.0 || $c < 0.0 || $c >= 1.0) {
            throw new InvalidArgumentException('Question IRT parameters are outside supported bounds.');
        }

        return [$a, $b, $c];
    }
}
