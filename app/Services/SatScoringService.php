<?php

namespace App\Services;

use Illuminate\Support\Collection;

class SatScoringService
{
    /**
     * Calculate score for a section (R&W or Math)
     *
     * @param  Collection  $module1Responses Each item should have 'is_correct' and 'question' relation with irt_a, irt_b, irt_c
     * @param  Collection  $module2Responses
     * @return array{scaled_score: int, theta: float, module2_path: string}
     */
    public function scoreSection(
        Collection $module1Responses,
        Collection $module2Responses
    ): array {
        // Step 1: Filter pretest questions
        $m1 = $module1Responses->filter(fn($r) => !$r->question->is_pretest);
        $m2 = $module2Responses->filter(fn($r) => !$r->question->is_pretest);

        // Step 2: Estimate Theta after Module 1 for routing
        $thetaM1 = $this->estimateTheta($m1);
        $m2Path = $this->routeModule2($thetaM1);

        // Step 3: Final Theta using all responses
        $allResponses = $m1->concat($m2);
        $thetaFinal = $this->estimateTheta($allResponses);

        // Step 4: Convert Theta to scaled score
        $scaledScore = $this->thetaToScaledScore($thetaFinal, $m2Path);

        return [
            'scaled_score' => $scaledScore,
            'theta' => round($thetaFinal, 3),
            'module2_path' => $m2Path,
        ];
    }

    /**
     * Calculate total SAT score
     */
    public function scoreFull(
        Collection $rwM1, Collection $rwM2,
        Collection $mathM1, Collection $mathM2
    ): array {
        $rw = $this->scoreSection($rwM1, $rwM2);
        $math = $this->scoreSection($mathM1, $mathM2);

        return [
            'total_score' => $rw['scaled_score'] + $math['scaled_score'],
            'rw_score' => $rw['scaled_score'],
            'math_score' => $math['scaled_score'],
            'rw_theta' => $rw['theta'],
            'math_theta' => $math['theta'],
            'rw_path' => $rw['module2_path'],
            'math_path' => $math['module2_path'],
        ];
    }

    /**
     * Estimate theta using Maximum Likelihood Estimation (MLE) - Newton-Raphson
     *
     * @param  Collection  $responses  Each item needs: is_correct (bool), question (irt_a, irt_b, irt_c)
     * @return float  theta in range [-4.0, 4.0]
     */
    public function estimateTheta(Collection $responses): float
    {
        $correctCount = $responses->where('is_correct', true)->count();
        $total = $responses->count();

        if ($total === 0) return 0.0;
        
        // Edge cases: All correct or all wrong
        if ($correctCount === $total) return 3.5;
        if ($correctCount === 0) return -3.5;

        $theta = 0.0;

        for ($iter = 0; $iter < 30; $iter++) {
            $numerator = 0.0;
            $denominator = 0.0;

            foreach ($responses as $r) {
                $a = (float) $r->question->irt_a;
                $b = (float) $r->question->irt_b;
                $c = (float) $r->question->irt_c;

                // Probability of correct response (3PL Model)
                $p = $c + (1 - $c) / (1 + exp(-$a * ($theta - $b)));
                $q = 1 - $p;

                // Avoid division by zero
                if ($p * $q < 1e-10 || ($p - $c) < 1e-10) continue;

                // First derivative of log-likelihood
                $numerator += $a * ($r->is_correct - $p) * (($p - $c) / ((1 - $c) * $p));
                
                // Second derivative of log-likelihood (Fisher Information)
                $denominator += ($a ** 2) * (($p - $c) ** 2) / ((1 - $c) ** 2 * $p * $q);
            }

            if ($denominator < 1e-10) break;

            $delta = $numerator / $denominator;
            $theta += $delta;

            // Convergence check
            if (abs($delta) < 0.001) break;
        }

        return max(-4.0, min(4.0, $theta));
    }

    /**
     * Route to Module 2 difficulty based on M1 theta
     */
    public function routeModule2(float $thetaM1): string
    {
        return $thetaM1 >= 0.0 ? 'hard' : 'easy';
    }

    /**
     * Convert theta to scaled score (200-800) using Sigmoid function
     */
    public function thetaToScaledScore(float $theta, string $module2): int
    {
        // Edge cases for max/min ability
        if ($theta >= 3.5) return $module2 === 'hard' ? 800 : 640;
        if ($theta <= -3.5) return 200;

        // Hard path: 200-800, Easy path: 200-640 (approx ceiling)
        $maxScore = $module2 === 'hard' ? 800 : 640;
        $minScore = 200;
        $range = $maxScore - $minScore;

        // Sigmoid mapping
        $sigmoid = 1 / (1 + exp(-1.2 * $theta));
        
        $scaled = $minScore + ($sigmoid * $range);
        
        // Round to nearest 10
        $scaled = round($scaled / 10) * 10;

        return (int) max($minScore, min($maxScore, $scaled));
    }
}
