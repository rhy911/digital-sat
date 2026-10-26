<?php

namespace Tests\Unit;

use App\Models\UserTest;
use App\Services\SatScoringPipeline;
use Tests\TestCase;

class SatScoringPipelineTest extends TestCase
{
    private SatScoringPipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = app(SatScoringPipeline::class);
    }

    public function test_pipeline_resolves_from_container(): void
    {
        $this->assertInstanceOf(SatScoringPipeline::class, $this->pipeline);
    }

    public function test_rescore_legacy_returns_skip_when_not_completed(): void
    {
        $attempt = new UserTest([
            'status' => 'in_progress',
            'score_conversion_version' => 'generic_ds_v1',
            'score_conversion_set_id' => null,
        ]);

        $this->assertSame(['status' => 'skipped', 'reason' => 'not_completed_or_eligible_generic_score'], $this->pipeline->rescoreLegacy($attempt));
    }

    public function test_rescore_legacy_returns_skip_when_not_generic_ds_v1(): void
    {
        $attempt = new UserTest([
            'status' => 'completed',
            'score_conversion_version' => 'normal_consensus_v1',
            'score_conversion_set_id' => null,
        ]);

        $this->assertSame(['status' => 'skipped', 'reason' => 'not_completed_or_eligible_generic_score'], $this->pipeline->rescoreLegacy($attempt));
    }

    public function test_rescore_legacy_returns_skip_when_conversion_set_id_present(): void
    {
        $attempt = new UserTest([
            'status' => 'completed',
            'score_conversion_version' => 'generic_ds_v1',
            'score_conversion_set_id' => 123,
        ]);

        $this->assertSame(['status' => 'skipped', 'reason' => 'not_completed_or_eligible_generic_score'], $this->pipeline->rescoreLegacy($attempt));
    }
}
