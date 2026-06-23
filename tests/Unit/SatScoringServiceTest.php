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
