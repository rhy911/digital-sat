<?php

namespace Tests\Unit;

use App\Http\Controllers\Engine\Concerns\HandlesAnswers;
use App\Models\Question;
use App\Models\SprCorrectAnswer;
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
        $checker = new class
        {
            use HandlesAnswers;

            public function check(Question $question, string $answer): bool
            {
                return $this->checkAnswer($question, $answer);
            }
        };

        $this->assertTrue($checker->check($question, $submitted));
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
