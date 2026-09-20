<?php

namespace Tests\Unit;

use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\SprCorrectAnswer;
use App\Services\ExamAttemptEngine;
use PHPUnit\Framework\TestCase;

class ExamAttemptEngineTest extends TestCase
{
    private ExamAttemptEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new ExamAttemptEngine();
    }

    public function test_parse_numeric_answer_handles_integers_and_decimals(): void
    {
        $this->assertSame(42.0, $this->engine->parseNumericAnswer('42'));
        $this->assertSame(3.14159, $this->engine->parseNumericAnswer('3.14159'));
        $this->assertSame(-17.5, $this->engine->parseNumericAnswer('-17.5'));
        // Unicode minus signs
        $this->assertSame(-5.2, $this->engine->parseNumericAnswer("−5.2"));
        $this->assertSame(-5.2, $this->engine->parseNumericAnswer("–5.2"));
    }

    public function test_parse_numeric_answer_handles_simple_and_mixed_fractions(): void
    {
        $this->assertSame(0.75, $this->engine->parseNumericAnswer('3/4'));
        $this->assertSame(-0.5, $this->engine->parseNumericAnswer('-1/2'));
        $this->assertSame(2.5, $this->engine->parseNumericAnswer('2 1/2'));
        $this->assertSame(-2.5, $this->engine->parseNumericAnswer('-2 1/2'));
    }

    public function test_parse_numeric_answer_rejects_invalid_inputs(): void
    {
        $this->assertNull($this->engine->parseNumericAnswer(''));
        $this->assertNull($this->engine->parseNumericAnswer('abc'));
        $this->assertNull($this->engine->parseNumericAnswer('3/0'));
        $this->assertNull($this->engine->parseNumericAnswer('1 2/0'));
    }

    public function test_duration_seconds_defaults_correctly(): void
    {
        $mathModule = new Module();
        $mathSection = new Section(['type' => Section::TYPE_MATH]);
        $mathModule->setRelation('section', $mathSection);

        $rwModule = new Module();
        $rwSection = new Section(['type' => Section::TYPE_RW]);
        $rwModule->setRelation('section', $rwSection);

        $customModule = new Module(['duration_minutes' => 40]);

        $this->assertSame(35 * 60, $this->engine->durationSeconds($mathModule));
        $this->assertSame(32 * 60, $this->engine->durationSeconds($rwModule));
        $this->assertSame(40 * 60, $this->engine->durationSeconds($customModule));
    }
}
