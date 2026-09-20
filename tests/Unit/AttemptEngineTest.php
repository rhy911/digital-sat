<?php

namespace Tests\Unit;

use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\SprCorrectAnswer;
use App\Models\UserTest;
use App\Services\AttemptEngine;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttemptEngineTest extends TestCase
{
    private AttemptEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new AttemptEngine();
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

    public function test_check_answer_evaluates_multiple_choice(): void
    {
        $question = new Question(['question_type' => 'multiple_choice']);
        $choiceA = new AnswerChoice(['label' => 'A', 'is_correct' => false]);
        $choiceB = new AnswerChoice(['label' => 'B', 'is_correct' => true]);
        $question->setRelation('answerChoices', collect([$choiceA, $choiceB]));

        $this->assertTrue($this->engine->checkAnswer($question, 'B'));
        $this->assertTrue($this->engine->checkAnswer($question, ' B '));
        $this->assertFalse($this->engine->checkAnswer($question, 'A'));
        $this->assertFalse($this->engine->checkAnswer($question, 'C'));
    }

    public function test_check_answer_evaluates_student_produced_response(): void
    {
        $question = new Question(['question_type' => 'student_produced_response']);
        $spr = new SprCorrectAnswer(['answer' => '3/4', 'tolerance' => null]);
        $question->setRelation('sprCorrectAnswers', collect([$spr]));

        // Fraction & decimal equivalence
        $this->assertTrue($this->engine->checkAnswer($question, '3/4'));
        $this->assertTrue($this->engine->checkAnswer($question, '0.75'));
        $this->assertTrue($this->engine->checkAnswer($question, '6/8'));
        // Within default tolerance (0.0001)
        $this->assertTrue($this->engine->checkAnswer($question, '0.75005'));
        // Outside tolerance
        $this->assertFalse($this->engine->checkAnswer($question, '0.76'));

        // Custom tolerance
        $tolQuestion = new Question(['question_type' => 'spr']);
        $tolSpr = new SprCorrectAnswer(['answer' => '10.0', 'tolerance' => 0.5]);
        $tolQuestion->setRelation('sprCorrectAnswers', collect([$tolSpr]));

        $this->assertTrue($this->engine->checkAnswer($tolQuestion, '10.4'));
        $this->assertFalse($this->engine->checkAnswer($tolQuestion, '10.8'));
    }

    public function test_check_answer_throws_on_unsupported_question_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $question = new Question(['question_type' => 'unsupported_type']);
        $this->engine->checkAnswer($question, 'any');
    }

    public function test_deadline_and_timing_calculations(): void
    {
        $module = new Module(['duration_minutes' => 30]);
        $attempt = new UserTest();

        // When not started
        $this->assertNull($this->engine->deadline($attempt, $module));
        $initialTiming = $this->engine->timing($attempt, $module);
        $this->assertSame(1800, $initialTiming['duration_seconds']);
        $this->assertSame(0, $initialTiming['elapsed_seconds']);
        $this->assertSame(1800, $initialTiming['remaining_seconds']);
        $this->assertFalse($initialTiming['expired']);

        // When started
        $startedAt = Carbon::parse('2026-09-20 10:00:00');
        $attempt->current_module_started_at = $startedAt;

        $deadline = $this->engine->deadline($attempt, $module);
        $this->assertNotNull($deadline);
        $this->assertSame('2026-09-20 10:30:00', $deadline->format('Y-m-d H:i:s'));

        // Mid-module timing (10 mins in)
        $midTiming = $this->engine->timing($attempt, $module, Carbon::parse('2026-09-20 10:10:00'));
        $this->assertSame(600, $midTiming['elapsed_seconds']);
        $this->assertSame(1200, $midTiming['remaining_seconds']);
        $this->assertFalse($midTiming['expired']);

        // Expired module timing (35 mins in)
        $pastTiming = $this->engine->timing($attempt, $module, Carbon::parse('2026-09-20 10:35:00'));
        $this->assertSame(1800, $pastTiming['elapsed_seconds']);
        $this->assertSame(0, $pastTiming['remaining_seconds']);
        $this->assertTrue($pastTiming['expired']);
    }

    public function test_sync_elapsed_chained_clock_calculates_and_flags_expiration(): void
    {
        $module = new Module(['duration_minutes' => 30]);
        $attempt = new UserTest([
            'assignment_id' => 123, // runsOnChainedClock() returns true
            'current_module_started_at' => Carbon::parse('2026-09-20 10:00:00'),
        ]);

        // Freeze time at 15 minutes in
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:15:00'));
        $timing = $this->engine->syncElapsed($attempt, $module);

        $this->assertSame(900, $timing['elapsed_seconds']);
        $this->assertSame(900, $timing['remaining_seconds']);
        $this->assertFalse($timing['expired']);

        // Fast-forward past deadline (35 minutes in)
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:35:00'));
        $expiredTiming = $this->engine->syncElapsed($attempt, $module);

        $this->assertSame(1800, $expiredTiming['elapsed_seconds']);
        $this->assertSame(0, $expiredTiming['remaining_seconds']);
        $this->assertTrue($expiredTiming['expired']);

        Carbon::setTestNow();
    }

    public function test_sync_elapsed_paused_clock_preserves_manual_elapsed(): void
    {
        $module = new Module(['duration_minutes' => 30]);
        // Practice attempt (no assignment_id or exam_session_id)
        $attempt = new UserTest([
            'current_module_elapsed_seconds' => 300,
        ]);

        $timing = $this->engine->syncElapsed($attempt, $module);

        $this->assertSame(300, $timing['elapsed_seconds']);
        $this->assertSame(1500, $timing['remaining_seconds']);
        $this->assertFalse($timing['expired']);
    }

    public function test_assert_issued_throws_conflict_http_exception_on_mismatch(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);
        $this->expectExceptionMessage('This module is not active for the test attempt.');

        $attempt = new UserTest(['current_module_id' => 10]);
        $attempt->id = 1;

        $module = new Module();
        $module->id = 20;

        $this->engine->assertIssued($attempt, $module);
    }

    public function test_assert_issued_passes_when_module_matches(): void
    {
        $attempt = new UserTest(['current_module_id' => 10]);
        $attempt->id = 1;

        $module = new Module();
        $module->id = 10;

        $this->engine->assertIssued($attempt, $module);
        $this->assertSame(10, (int) $attempt->current_module_id);
    }
}
