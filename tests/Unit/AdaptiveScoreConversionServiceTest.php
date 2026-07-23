<?php

namespace Tests\Unit;

use App\Services\AdaptiveScoreConversionService;
use Tests\TestCase;

class AdaptiveScoreConversionServiceTest extends TestCase
{
    public function test_theta_mapping_is_versioned_and_returns_uncertainty(): void
    {
        $service = app(AdaptiveScoreConversionService::class);
        $section = $service->convert(1.1, 0.4, 'reading_writing', 'hard');

        $this->assertSame(610, $section['scaled_score']);
        $this->assertSame(570, $section['lower']);
        $this->assertSame(650, $section['upper']);
        $this->assertSame('irt_curve_v1', $section['conversion_version']);
        $this->assertSame('adaptive_irt_provisional', $section['estimate_kind']);
    }

    public function test_total_range_combines_section_standard_errors(): void
    {
        $service = app(AdaptiveScoreConversionService::class);
        $total = $service->totalRange(
            $service->convert(1.0, 0.3, 'reading_writing', 'hard'),
            $service->convert(0.5, 0.4, 'math', 'hard'),
        );

        $this->assertSame(1150, $total['score']);
        $this->assertSame(1100, $total['lower']);
        $this->assertSame(1200, $total['upper']);
    }

    public function test_easy_path_caps_the_section_below_the_hard_ceiling(): void
    {
        $service = app(AdaptiveScoreConversionService::class);

        // A near-perfect easy-path ability cannot reach the hard ceiling.
        $this->assertSame(670, $service->convert(3.0, 0.1, 'reading_writing', 'easy')['scaled_score']);
        $this->assertSame(660, $service->convert(3.0, 0.1, 'math', 'easy')['scaled_score']);
    }

    public function test_hard_path_reaches_800_and_sections_differ(): void
    {
        $service = app(AdaptiveScoreConversionService::class);

        // Hard path opens the full range to 800.
        $this->assertSame(800, $service->convert(3.3, 0.1, 'reading_writing', 'hard')['scaled_score']);
        $this->assertSame(800, $service->convert(3.0, 0.1, 'math', 'hard')['scaled_score']);

        // Reading & Writing and Math use distinct curves (Math steeper at the top).
        $this->assertSame(700, $service->convert(2.0, 0.1, 'reading_writing', 'hard')['scaled_score']);
        $this->assertSame(720, $service->convert(2.0, 0.1, 'math', 'hard')['scaled_score']);
    }

    public function test_unknown_path_defaults_to_hard_full_range(): void
    {
        $service = app(AdaptiveScoreConversionService::class);

        $this->assertSame(
            $service->convert(2.0, 0.1, 'reading_writing', 'hard')['scaled_score'],
            $service->convert(2.0, 0.1, 'reading_writing', null)['scaled_score'],
        );
    }
}
