<?php

namespace Tests\Unit;

use App\Services\DefaultScoreConversionService;
use RuntimeException;
use Tests\TestCase;

class DefaultScoreConversionServiceTest extends TestCase
{
    public function test_consensus_golden_vector_returns_1270_without_route_bonus(): void
    {
        $service = app(DefaultScoreConversionService::class);
        $rw = $service->convert('reading_writing', 45, 54);
        $math = $service->convert('math', 35, 44);

        $this->assertSame(630, $rw['scaled_score']);
        $this->assertSame(640, $math['scaled_score']);
        $this->assertSame(1270, $rw['scaled_score'] + $math['scaled_score']);
        $this->assertSame('normal_consensus_v1', $rw['conversion_version']);
        $this->assertSame('normal_generic', $rw['estimate_kind']);
    }

    public function test_normal_conversion_requires_official_presented_question_total(): void
    {
        $this->expectException(RuntimeException::class);
        app(DefaultScoreConversionService::class)->convert('math', 1, 2);
    }
}
