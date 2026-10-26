<?php

namespace Tests\Unit;

use App\Services\SatScoringService;
use InvalidArgumentException;
use Tests\TestCase;

class SatScoringServiceTest extends TestCase
{
    private SatScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SatScoringService;
    }

    public function test_eap_handles_extreme_patterns_without_fixed_theta_constants(): void
    {
        $correct = $this->responses(array_fill(0, 20, true));
        $wrong = $this->responses(array_fill(0, 20, false));

        $high = $this->service->estimateAbility($correct);
        $low = $this->service->estimateAbility($wrong);

        $this->assertGreaterThan(1.0, $high['theta']);
        $this->assertLessThan(-1.0, $low['theta']);
        $this->assertNotEquals(3.5, $high['theta']);
        $this->assertNotEquals(-3.5, $low['theta']);
        $this->assertSame('eap_3pl_v1', $high['method']);
    }

    public function test_more_correct_answers_produce_higher_theta(): void
    {
        $low = $this->service->estimateTheta($this->responses([true, false, false, false, false, false]));
        $middle = $this->service->estimateTheta($this->responses([true, true, true, false, false, false]));
        $high = $this->service->estimateTheta($this->responses([true, true, true, true, true, false]));

        $this->assertLessThan($middle, $low);
        $this->assertLessThan($high, $middle);
    }

    public function test_experimental_mle_candidate_is_finite_and_preserves_extreme_score_boundaries(): void
    {
        $perfect = $this->service->estimateAbilityMle($this->responses(array_fill(0, 20, true)));
        $none = $this->service->estimateAbilityMle($this->responses(array_fill(0, 20, false)));

        $this->assertSame('mle_3pl_candidate_v2', $perfect['method']);
        $this->assertGreaterThan($none['theta'], $perfect['theta']);
        $this->assertLessThanOrEqual(8.0, $perfect['theta']);
        $this->assertGreaterThanOrEqual(-8.0, $none['theta']);
        $this->assertTrue(is_finite($perfect['se']));
        $this->assertTrue(is_finite($none['se']));
    }

    public function test_mle_search_finds_global_peak_for_multimodal_three_pl_likelihood(): void
    {
        $items = [
            [4.0, 4.0, 0.25, false],
            [4.0, -1.0, 0.25, true],
            [0.5, 0.0, 0.25, false],
            [0.5, -1.0, 0.25, false],
            [2.0, -5.0, 0.25, true],
        ];
        $responses = collect($items)->map(fn ($item) => (object) [
            'is_correct' => $item[3],
            'question' => (object) ['irt_a' => $item[0], 'irt_b' => $item[1], 'irt_c' => $item[2], 'is_pretest' => false],
        ]);

        $estimate = $this->service->estimateAbilityMle($responses);
        $likelihood = static function (float $theta) use ($items): float {
            $sum = 0.0;
            foreach ($items as [$a, $b, $c, $correct]) {
                $probability = $c + (1 - $c) / (1 + exp(-$a * ($theta - $b)));
                $sum += $correct ? log($probability) : log(1 - $probability);
            }

            return $sum;
        };
        $denseBest = -INF;
        for ($theta = -8.0; $theta <= 8.000001; $theta += 0.0001) {
            $denseBest = max($denseBest, $likelihood($theta));
        }

        $this->assertSame('mle_3pl_candidate_v2', $estimate['method']);
        $this->assertGreaterThan(-1.5, $estimate['theta'], 'The old golden-section search selected the inferior peak near -3.935508.');
        $this->assertGreaterThanOrEqual($denseBest - 1e-8, $likelihood($estimate['theta']));
    }

    public function test_posterior_uncertainty_shrinks_with_more_items(): void
    {
        $short = $this->service->estimateAbility($this->responses([true, false]));
        $long = $this->service->estimateAbility($this->responses(array_merge(array_fill(0, 10, true), array_fill(0, 10, false))));

        $this->assertLessThan($short['se'], $long['se']);
    }

    public function test_invalid_or_empty_item_data_fails_explicitly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->estimateAbility(collect());
    }

    public function test_invalid_parameters_do_not_fall_back_to_defaults(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->estimateAbility(collect([
            (object) ['is_correct' => true, 'question' => (object) ['irt_a' => null, 'irt_b' => 0.0, 'irt_c' => 0.25, 'is_pretest' => false]],
        ]));
    }

    public function test_routing_threshold_is_stable(): void
    {
        $this->assertSame('hard', $this->service->routeModule2(0.0));
        $this->assertSame('hard', $this->service->routeModule2(0.1));
        $this->assertSame('easy', $this->service->routeModule2(-0.1));
    }

    public function test_routing_cutoff_is_configurable_per_section(): void
    {
        config()->set('sat_scoring.routing.theta_cutoff', [
            'default' => 0.0,
            'reading_writing' => 0.5,
            'math' => -0.5,
        ]);

        // Reading & Writing cutoff 0.5.
        $this->assertSame('easy', $this->service->routeModule2(0.4, 'reading_writing'));
        $this->assertSame('hard', $this->service->routeModule2(0.6, 'reading_writing'));

        // Math cutoff -0.5.
        $this->assertSame('hard', $this->service->routeModule2(-0.4, 'math'));
        $this->assertSame('easy', $this->service->routeModule2(-0.6, 'math'));

        // Unknown / null section falls back to the default cutoff (0.0).
        $this->assertSame('hard', $this->service->routeModule2(0.0));
        $this->assertSame('easy', $this->service->routeModule2(-0.1));
    }

    private function responses(array $correctness)
    {
        return collect($correctness)->map(fn ($correct) => (object) [
            'is_correct' => $correct,
            'question' => (object) [
                'irt_a' => 0.9,
                'irt_b' => 0.0,
                'irt_c' => 0.25,
                'is_pretest' => false,
            ],
        ]);
    }
}
