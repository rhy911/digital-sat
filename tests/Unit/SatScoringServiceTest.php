<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Services\SatScoringService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SatScoringServiceTest extends TestCase
{
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SatScoringService();
    }

    public function test_estimate_theta_all_correct()
    {
        $responses = collect([
            (object)['is_correct' => true, 'question' => (object)['irt_a' => 0.9, 'irt_b' => 0.0, 'irt_c' => 0.25]],
            (object)['is_correct' => true, 'question' => (object)['irt_a' => 0.9, 'irt_b' => 0.0, 'irt_c' => 0.25]],
        ]);

        $theta = $this->service->estimateTheta($responses);
        $this->assertEquals(3.5, $theta);
    }

    public function test_estimate_theta_all_wrong()
    {
        $responses = collect([
            (object)['is_correct' => false, 'question' => (object)['irt_a' => 0.9, 'irt_b' => 0.0, 'irt_c' => 0.25]],
            (object)['is_correct' => false, 'question' => (object)['irt_a' => 0.9, 'irt_b' => 0.0, 'irt_c' => 0.25]],
        ]);

        $theta = $this->service->estimateTheta($responses);
        $this->assertEquals(-3.5, $theta);
    }

    public function test_theta_to_scaled_score_hard_max()
    {
        $score = $this->service->thetaToScaledScore(3.5, 'hard');
        $this->assertEquals(800, $score);
    }

    public function test_theta_to_scaled_score_easy_max()
    {
        $score = $this->service->thetaToScaledScore(3.5, 'easy');
        $this->assertEquals(640, $score);
    }

    public function test_routing_threshold()
    {
        $this->assertEquals('hard', $this->service->routeModule2(0.1));
        $this->assertEquals('easy', $this->service->routeModule2(-0.1));
    }
}
