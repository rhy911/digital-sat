<?php

namespace Tests\Unit;

use App\Services\AdaptiveScoreConversionService;
use App\Services\SatScoringService;
use Tests\TestCase;

class AdaptiveScoreBoundaryTest extends TestCase
{
    public function test_all_correct_hard_route_reaches_800_and_none_correct_reaches_200(): void
    {
        $scoring = app(SatScoringService::class);
        $conversion = app(AdaptiveScoreConversionService::class);

        foreach ([['reading_writing', 54], ['math', 44]] as [$section, $count]) {
            $correct = $this->responses($count, true);
            $wrong = $this->responses($count, false);

            $perfect = $conversion->convert($scoring->estimateAbility($correct)['theta'], 0, $section, 'hard', $count, $count);
            $empty = $conversion->convert($scoring->estimateAbility($wrong)['theta'], 0, $section, 'hard', 0, $count);

            $this->assertSame(800, $perfect['scaled_score'], "$section all-correct score");
            $this->assertSame(200, $empty['scaled_score'], "$section none-correct score");
        }
    }

    public function test_perfect_easy_route_keeps_the_configured_section_cap(): void
    {
        $scoring = app(SatScoringService::class);
        $conversion = app(AdaptiveScoreConversionService::class);

        $rw = $conversion->convert($scoring->estimateAbility($this->responses(54, true))['theta'], 0, 'reading_writing', 'easy', 54, 54);
        $math = $conversion->convert($scoring->estimateAbility($this->responses(44, true))['theta'], 0, 'math', 'easy', 44, 44);

        $this->assertLessThanOrEqual(670, $rw['scaled_score']);
        $this->assertLessThanOrEqual(660, $math['scaled_score']);
    }

    private function responses(int $count, bool $correct)
    {
        return collect(array_fill(0, $count, $correct))->map(fn ($isCorrect) => (object) [
            'is_correct' => $isCorrect,
            'question' => (object) ['is_pretest' => false, 'irt_a' => 0.9, 'irt_b' => 0.0, 'irt_c' => 0.25],
        ]);
    }
}
