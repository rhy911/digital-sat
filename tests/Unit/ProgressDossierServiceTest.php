<?php

namespace Tests\Unit;

use App\Models\Test;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Services\ProgressDossierService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProgressDossierServiceTest extends TestCase
{
    public function test_it_builds_longitudinal_metrics_from_frozen_question_snapshots(): void
    {
        $attempts = collect([
            $this->attempt(1100, 500, 600, 3, [true, false, false, true]),
            $this->attempt(1200, 520, 680, 2, [true, false, true, true]),
            $this->attempt(1280, 530, 750, 1, [true, true, true, false]),
            $this->sectionAttempt(),
        ]);

        $dossier = app(ProgressDossierService::class)->build($attempts, 1400);

        $this->assertSame(4, $dossier['activityAttemptCount']);
        $this->assertSame(3, $dossier['scoredFullTestCount']);
        $this->assertSame(1280, $dossier['score']['latest']['total']);
        $this->assertSame(180, $dossier['score']['growth']);
        $this->assertSame(120, $dossier['score']['targetGap']);
        $this->assertSame('Rapid growth', $dossier['score']['plateau']);

        $skill = collect($dossier['skills'])->firstWhere('skill', 'Inferences');
        $this->assertNotNull($skill);
        $this->assertSame(12, $skill['total']);
        $this->assertSame('Moderate', $skill['evidenceLevel']);
        $this->assertNotNull($skill['opportunityIndex']);
        $this->assertNotEmpty($dossier['stamina']);
    }

    private function attempt(int $total, int $rw, int $math, int $daysAgo, array $correctness): UserTest
    {
        $attempt = new UserTest([
            'attempt_type' => 'full',
            'total_score' => $total,
            'score_reading_writing' => $rw,
            'score_math' => $math,
            'completed_at' => now()->subDays($daysAgo),
        ]);
        $attempt->setRelation('test', new Test(['title' => "Mock {$total}"]));
        $answers = collect($correctness)->map(function (bool $correct, int $index) {
            $answer = new UserTestAnswer([
                'module_id' => 1,
                'question_id' => $index + 1,
                'selected_answer' => $correct ? 'A' : 'B',
                'is_correct' => $correct,
                'time_spent' => 50 + ($index * 10),
                'question_snapshot' => [
                    'section_type' => 'reading_writing',
                    'skill_domain' => 'information_and_ideas',
                    'skill_subdomain' => 'inferences',
                    'difficulty' => $index % 2 ? 'hard' : 'medium',
                    'expected_time' => 60,
                    'module_position' => $index + 1,
                    'is_pretest' => false,
                ],
            ]);
            $answer->setRelation('review', null);

            return $answer;
        });
        $attempt->setRelation('userAnswers', $answers);

        return $attempt;
    }

    private function sectionAttempt(): UserTest
    {
        $attempt = new UserTest([
            'attempt_type' => 'section',
            'score_math' => 700,
            'completed_at' => now(),
        ]);
        $attempt->setRelation('test', new Test(['title' => 'Math Section']));
        $attempt->setRelation('userAnswers', new Collection());

        return $attempt;
    }
}
