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
     * @template T of object
     * @param Collection<int, T> $module1Responses
     * @param Collection<int, T> $module2Responses
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

        // Only estimate Module 1 ability on its own when we still need to route; at
        // finalize the caller passes the already-decided path, so skip the extra EAP pass.
        $path = $m2Path ?? $this->routeModule2($this->estimateAbility($m1)['theta']);
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
     *
     * @template T of object
     * @param Collection<int, T> $responses
     */
    public function estimateTheta(Collection $responses): float
    {
        return $this->estimateAbility($responses)['theta'];
    }

    /**
     * Estimate ability with a standard-normal-prior EAP over a fixed theta grid.
     *
     * @template T of object
     * @param Collection<int, T> $responses
     * @return array{theta:float,se:float,method:string,item_count:int}
     */
    public function estimateAbility(Collection $responses): array
    {
        $responses = $this->scoredResponses($responses)->values();
        if ($responses->isEmpty()) {
            throw new InvalidArgumentException('Ability estimation requires at least one scored response.');
        }

        // Item parameters are constant across the theta grid, but reading them is
        // NOT cheap: $response->question runs UserTestAnswer::getQuestionAttribute(),
        // which json_decodes the snapshot and rebuilds a Question plus its passage,
        // answer choices and SPR answers on EVERY access, with no memoisation.
        // Called inside the loop below that was 161 grid points x N responses object
        // graph rebuilds per estimate — ~8,000 for a 50-item section, and the single
        // largest cost in the whole scoring path. Hoisted out; the arithmetic and
        // the resulting theta are unchanged.
        $items = [];
        foreach ($responses as $response) {
            [$a, $b, $c] = $this->itemParameters($response);
            $items[] = [$a, $b, $c, (bool) $response->is_correct];
        }

        $logPosteriors = [];
        for ($theta = self::GRID_MIN; $theta <= self::GRID_MAX + 1e-9; $theta += self::GRID_STEP) {
            $logPosterior = -0.5 * ($theta ** 2);
            foreach ($items as [$a, $b, $c, $isCorrect]) {
                $probability = $c + (1 - $c) / (1 + exp(-$a * ($theta - $b)));
                $probability = max(1e-12, min(1 - 1e-12, $probability));
                $logPosterior += $isCorrect
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

    /**
     * Experimental no-prior 3PL MLE for offline score comparison only.
     *
     * @template T of object
     * @param Collection<int, T> $responses
     * @return array{theta:float,se:float,method:string,item_count:int}
     */
    public function estimateAbilityMle(Collection $responses): array
    {
        $responses = $this->scoredResponses($responses)->values();
        if ($responses->isEmpty()) {
            throw new InvalidArgumentException('Ability estimation requires at least one scored response.');
        }

        $items = [];
        foreach ($responses as $response) {
            [$a, $b, $c] = $this->itemParameters($response);
            $items[] = [$a, $b, $c, (bool) $response->is_correct];
        }

        $logLikelihood = static function (float $theta) use ($items): float {
            $value = 0.0;
            foreach ($items as [$a, $b, $c, $correct]) {
                $p = max(1e-12, min(1 - 1e-12, $c + (1 - $c) / (1 + exp(-$a * ($theta - $b)))));
                $value += $correct ? log($p) : log(1 - $p);
            }

            return $value;
        };

        // The 3PL likelihood can have more than one peak. Scan first, then refine
        // every sampled local maximum instead of assuming one global unimodal peak.
        $grid = [];
        for ($theta = -8.0; $theta <= 8.000001; $theta += 0.01) {
            $grid[] = ['theta' => $theta, 'log_likelihood' => $logLikelihood($theta)];
        }
        $candidates = $grid;
        $ratio = (sqrt(5) - 1) / 2;
        for ($index = 1, $last = count($grid) - 1; $index < $last; $index++) {
            if ($grid[$index]['log_likelihood'] < $grid[$index - 1]['log_likelihood']
                || $grid[$index]['log_likelihood'] < $grid[$index + 1]['log_likelihood']) {
                continue;
            }

            $left = $grid[$index - 1]['theta'];
            $right = $grid[$index + 1]['theta'];
            $x1 = $right - $ratio * ($right - $left);
            $x2 = $left + $ratio * ($right - $left);
            $f1 = $logLikelihood($x1);
            $f2 = $logLikelihood($x2);
            for ($iteration = 0; $iteration < 60; $iteration++) {
                if ($f1 < $f2) {
                    $left = $x1;
                    $x1 = $x2;
                    $f1 = $f2;
                    $x2 = $left + $ratio * ($right - $left);
                    $f2 = $logLikelihood($x2);
                } else {
                    $right = $x2;
                    $x2 = $x1;
                    $f2 = $f1;
                    $x1 = $right - $ratio * ($right - $left);
                    $f1 = $logLikelihood($x1);
                }
            }
            $theta = ($left + $right) / 2;
            $candidates[] = ['theta' => $theta, 'log_likelihood' => $logLikelihood($theta)];
        }
        $candidates[] = ['theta' => -8.0, 'log_likelihood' => $logLikelihood(-8.0)];
        $candidates[] = ['theta' => 8.0, 'log_likelihood' => $logLikelihood(8.0)];
        usort($candidates, static function (array $left, array $right): int {
            $difference = $right['log_likelihood'] - $left['log_likelihood'];

            return abs($difference) <= 1e-12 ? ($left['theta'] <=> $right['theta']) : ($difference <=> 0.0);
        });
        $theta = $candidates[0]['theta'];
        $information = 0.0;
        foreach ($items as [$a, $b, $c]) {
            $logistic = 1 / (1 + exp(-$a * ($theta - $b)));
            $p = max(1e-12, min(1 - 1e-12, $c + (1 - $c) * $logistic));
            $derivative = (1 - $c) * $a * $logistic * (1 - $logistic);
            $information += ($derivative ** 2) / ($p * (1 - $p));
        }

        return [
            'theta' => round($theta, 6),
            'se' => round(min(4.0, sqrt(1 / max($information, 1e-8))), 6),
            'method' => 'mle_3pl_candidate_v2',
            'item_count' => $responses->count(),
        ];
    }

    public function routeModule2(float $thetaM1, ?string $sectionType = null): string
    {
        return $thetaM1 >= $this->routingCutoff($sectionType)
            ? Module::DIFFICULTY_HARD
            : Module::DIFFICULTY_EASY;
    }

    /**
     * Global per-section theta cutoff for Module 2 routing, from config. Falls back to
     * the configured default when the section is unknown or has no explicit cutoff.
     */
    private function routingCutoff(?string $sectionType): float
    {
        $cutoffs = config('sat_scoring.routing.theta_cutoff', []);
        if ($sectionType !== null && isset($cutoffs[$sectionType]) && is_numeric($cutoffs[$sectionType])) {
            return (float) $cutoffs[$sectionType];
        }

        return (float) ($cutoffs['default'] ?? 0.0);
    }

    /**
     * @template T of object
     * @param Collection<int, T> $responses
     * @return Collection<int, T>
     */
    private function scoredResponses(Collection $responses): Collection
    {
        /** @var Collection<int, T> $filtered */
        $filtered = $responses->filter(fn ($response) => ! (bool) $response->question?->is_pretest);

        return $filtered;
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
