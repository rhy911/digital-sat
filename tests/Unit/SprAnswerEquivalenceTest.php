<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Models\SprCorrectAnswer;
use App\Services\AttemptEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SprAnswerEquivalenceTest extends TestCase
{
    #[DataProvider('equivalentAnswers')]
    public function test_numeric_spr_equivalence(string $accepted, string $submitted, ?float $tolerance = null): void
    {
        $question = new Question(['question_type' => Question::TYPE_SPR]);
        $question->setRelation('sprCorrectAnswers', collect([
            new SprCorrectAnswer(['answer' => $accepted, 'answer_type' => 'exact', 'tolerance' => $tolerance]),
        ]));

        $engine = new class(app(\App\Services\TestStructureService::class), app(\App\Services\SatScoringPipeline::class)) extends AttemptEngine {
            public function check(Question $question, $userAnswer): bool
            {
                return $this->checkAnswer($question, $userAnswer);
            }
        };

        $this->assertTrue($engine->check($question, $submitted));
    }

    public static function equivalentAnswers(): array
    {
        return [
            'fraction' => ['0.5', '1/2'],
            'trailing zeros' => ['2.50', '2.5'],
            'negative' => ['-3', '-3.0'],
            'mixed number' => ['1.5', '1 1/2'],
            'negative mixed number' => ['-1.5', '-1 1/2'],
            'stored tolerance' => ['3.14', '3.141', 0.01],
        ];
    }
}
