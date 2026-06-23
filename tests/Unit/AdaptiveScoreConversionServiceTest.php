<?php

namespace Tests\Unit;

use App\Services\AdaptiveScoreConversionService;
use Tests\TestCase;

class AdaptiveScoreConversionServiceTest extends TestCase
{
    public function test_theta_mapping_is_versioned_and_returns_uncertainty(): void
    {
        $service = app(AdaptiveScoreConversionService::class);
        $section = $service->convert(1.1, 0.4);

        $this->assertSame(610, $section['scaled_score']);
        $this->assertSame(570, $section['lower']);
        $this->assertSame(650, $section['upper']);
        $this->assertSame('provisional_irt_v1', $section['conversion_version']);
        $this->assertSame('adaptive_irt_provisional', $section['estimate_kind']);
    }

    public function test_total_range_combines_section_standard_errors(): void
    {
        $service = app(AdaptiveScoreConversionService::class);
        $total = $service->totalRange($service->convert(1.0, 0.3), $service->convert(0.5, 0.4));

        $this->assertSame(1150, $total['score']);
        $this->assertSame(1100, $total['lower']);
        $this->assertSame(1200, $total['upper']);
    }
}
