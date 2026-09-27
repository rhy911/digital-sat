<?php

namespace Tests\Feature;

use App\Models\Test;
use App\Services\ScoreConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ScoreConversionFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_consensus_golden_vector_returns_1270_without_route_bonus(): void
    {
        $test = Test::create([
            'title' => 'Lookup Test',
            'test_type' => Test::TYPE_FULL,
            'status' => 'active',
        ]);
        $service = app(ScoreConversionService::class);

        $rw = $service->convert($test, 'reading_writing', 45, 54);
        $math = $service->convert($test, 'math', 35, 44);

        $this->assertSame(630, $rw['scaled_score']);
        $this->assertSame(640, $math['scaled_score']);
        $this->assertSame(1270, $rw['scaled_score'] + $math['scaled_score']);
        $this->assertSame('normal_consensus_v1', $rw['conversion_version']);
        $this->assertSame('normal_generic', $rw['estimate_kind']);
    }

    public function test_normal_conversion_requires_official_presented_question_total(): void
    {
        $test = Test::create([
            'title' => 'Lookup Test',
            'test_type' => Test::TYPE_FULL,
            'status' => 'active',
        ]);
        $this->expectException(RuntimeException::class);
        app(ScoreConversionService::class)->convert($test, 'math', 1, 2);
    }
}
