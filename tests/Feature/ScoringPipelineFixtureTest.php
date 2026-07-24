<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ScoringFormBuilder;
use Tests\TestCase;

/**
 * Golden-master scoring fixtures: 3 complete forms played end-to-end through the
 * real progression + IRT scoring services (SatScoringService EAP theta ->
 * AdaptiveScoreConversionService path-aware curve). Every expected number below was
 * captured from the live implementation on a deterministic fixture, then pinned as a
 * regression baseline — any drift in the routing cutoff, EAP grid, or conversion
 * curves breaks these assertions.
 *
 * To intentionally re-baseline after a scoring change: temporarily dump
 * $attempt's score fields, confirm the new numbers are correct, and update the
 * constants here.
 */
class ScoringPipelineFixtureTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->student()->create(['email_verified_at' => now()]);
    }

    public function test_adaptive_high_performer_routes_hard_and_scores_high(): void
    {
        $test = ScoringFormBuilder::adaptiveFull('Adaptive High');
        $attempt = ScoringFormBuilder::play($test, $this->student(), fn ($m) => match (true) {
            $m->section->type === 'reading_writing' && (int) $m->module_number === 1 => 24, // 24/25
            $m->section->type === 'reading_writing' => 23,                                   // 23/25 M2
            (int) $m->module_number === 1 => 19,                                             // math 19/20
            default => 18,                                                                   // math 18/20 M2
        });

        $this->assertSame('completed', $attempt->status);
        $this->assertSame('hard', $attempt->rw_m2_path);
        $this->assertSame('hard', $attempt->math_m2_path);

        $this->assertSame(2.429, (float) $attempt->rw_theta);
        $this->assertSame(2.235, (float) $attempt->math_theta);

        $this->assertSame(750, $attempt->score_reading_writing);
        $this->assertSame([700, 790], [$attempt->score_reading_writing_lower, $attempt->score_reading_writing_upper]);
        $this->assertSame(740, $attempt->score_math);
        $this->assertSame([700, 780], [$attempt->score_math_lower, $attempt->score_math_upper]);

        $this->assertSame(1490, $attempt->total_score);
        $this->assertSame([1430, 1550], [$attempt->total_score_lower, $attempt->total_score_upper]);
        $this->assertSame($attempt->score_reading_writing + $attempt->score_math, $attempt->total_score);
        $this->assertSame('irt_curve_v1', $attempt->score_conversion_version);
        $this->assertSame('adaptive_irt_provisional', $attempt->score_estimate_kind);
    }

    public function test_adaptive_low_performer_routes_easy_and_caps_section(): void
    {
        $test = ScoringFormBuilder::adaptiveFull('Adaptive Low');
        $attempt = ScoringFormBuilder::play($test, $this->student(), fn ($m) => match (true) {
            $m->section->type === 'reading_writing' && (int) $m->module_number === 1 => 6,  // 6/25 -> easy
            $m->section->type === 'reading_writing' => 10,                                   // 10/25 M2 easy
            (int) $m->module_number === 1 => 5,                                              // math 5/20 -> easy
            default => 9,                                                                    // math 9/20 M2 easy
        });

        $this->assertSame('easy', $attempt->rw_m2_path);
        $this->assertSame('easy', $attempt->math_m2_path);

        $this->assertSame(-1.912, (float) $attempt->rw_theta);
        $this->assertSame(-1.306, (float) $attempt->math_theta);

        $this->assertSame(330, $attempt->score_reading_writing);
        $this->assertSame(380, $attempt->score_math);
        $this->assertSame(710, $attempt->total_score);

        // Easy Module 2 can never exceed the config cap (~670 RW / ~660 Math).
        $this->assertLessThanOrEqual(670, $attempt->score_reading_writing);
        $this->assertLessThanOrEqual(660, $attempt->score_math);
    }

    public function test_normal_full_mid_performer_uses_full_range_curve(): void
    {
        // Normal forms have no pretest, so every question is scored: 27 RW / 22 Math.
        $test = ScoringFormBuilder::normalFull('Normal Mid');
        $attempt = ScoringFormBuilder::play($test, $this->student(), fn ($m) => match (true) {
            $m->section->type === 'reading_writing' && (int) $m->module_number === 1 => 20, // 20/27
            $m->section->type === 'reading_writing' => 19,                                   // 19/27 M2
            (int) $m->module_number === 1 => 14,                                             // math 14/22
            default => 13,                                                                   // math 13/22 M2
        });
        $this->assertSame('completed', $attempt->status);
        $this->assertNull($attempt->rw_m2_path);   // normal (linear) has no routing
        $this->assertNull($attempt->math_m2_path);

        $this->assertSame(0.634, (float) $attempt->rw_theta);
        $this->assertSame(0.194, (float) $attempt->math_theta);

        $this->assertSame(560, $attempt->score_reading_writing);
        $this->assertSame(520, $attempt->score_math);
        $this->assertSame(1080, $attempt->total_score);
        $this->assertSame($attempt->score_reading_writing + $attempt->score_math, $attempt->total_score);
    }

    public function test_scoring_is_deterministic_across_runs(): void
    {
        $spec = fn ($m) => (int) $m->module_number === 1 ? 20 : 18;

        $first = ScoringFormBuilder::play(ScoringFormBuilder::adaptiveFull('Determinism A'), $this->student(), $spec);
        $second = ScoringFormBuilder::play(ScoringFormBuilder::adaptiveFull('Determinism B'), $this->student(), $spec);

        $this->assertSame($first->rw_theta, $second->rw_theta);
        $this->assertSame($first->score_reading_writing, $second->score_reading_writing);
        $this->assertSame($first->score_math, $second->score_math);
        $this->assertSame($first->total_score, $second->total_score);
    }
}
