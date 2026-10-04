<?php

namespace Tests\Unit;

use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\SprCorrectAnswer;
use App\Models\UserTest;
use App\Services\AttemptEngine;
use App\Services\SatScoringPipeline;
use App\Services\TestStructureService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TestableAttemptEngine extends AttemptEngine
{
    public function checkAnswer(Question $question, $userAnswer): bool
    {
        return parent::checkAnswer($question, $userAnswer);
    }

    public function parseNumericAnswer(string $value): ?float
    {
        return parent::parseNumericAnswer($value);
    }
}

class AttemptEngineTest extends TestCase
{
    private TestableAttemptEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new TestableAttemptEngine(app(TestStructureService::class), app(SatScoringPipeline::class));
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

    public function test_issue_initial_module_throws_unprocessable_entity_http_exception_when_test_has_no_modules(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Test has no module.');

        $test = new \App\Models\Test();
        $test->id = 999;
        $test->setRelation('sections', collect([]));
        $attempt = new UserTest();

        $this->engine->issueInitialModule($attempt, $test);
    }

    public function test_is_past_grace_deadline_evaluates_correctly(): void
    {
        $module = new Module(['duration_minutes' => 30]);
        $attempt = new UserTest();

        // Not started
        $this->assertFalse($this->engine->isPastGraceDeadline($attempt, $module));

        // Started 10 minutes ago (duration 30m, grace 5m -> deadline is in 20m)
        $attempt->current_module_started_at = Carbon::now()->subMinutes(10);
        $this->assertFalse($this->engine->isPastGraceDeadline($attempt, $module));

        // Started 32 minutes ago (past 30m duration, but within 5m grace -> 35m total)
        $attempt->current_module_started_at = Carbon::now()->subMinutes(32);
        $this->assertFalse($this->engine->isPastGraceDeadline($attempt, $module));

        // Started 36 minutes ago (past 30m duration + 5m grace)
        $attempt->current_module_started_at = Carbon::now()->subMinutes(36);
        $this->assertTrue($this->engine->isPastGraceDeadline($attempt, $module));
    }

    public function test_cascade_expire_returns_zero_when_not_in_progress_or_clock_future(): void
    {
        $attempt = new UserTest([
            'status' => 'completed',
            'assignment_id' => 1,
        ]);

        $this->assertSame(0, $this->engine->cascadeExpire($attempt));

        $runningAttempt = new UserTest([
            'status' => 'in_progress',
            'assignment_id' => 1,
        ]);

        // Future deadline
        $this->assertSame(0, $this->engine->cascadeExpire($runningAttempt, Carbon::now()->addHour()));
    }

    public function test_candidate_cascade_attempts_query_filters_chained_clock_and_in_progress_status(): void
    {
        $query = $this->engine->candidateCascadeAttemptsQuery();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);

        $sql = $query->toSql();
        $this->assertStringContainsString('status', $sql);
        $this->assertStringContainsString('current_module_id', $sql);
        $this->assertStringContainsString('current_module_started_at', $sql);
        $this->assertStringContainsString('assignment_id', $sql);
        $this->assertStringContainsString('exam_session_id', $sql);
    }

    public function test_submit_marker_key_format(): void
    {
        $this->assertSame('module_submit_active_12_34', AttemptEngine::submitMarkerKey(12, 34));
    }

    public function test_record_failure_stores_cache_and_clears_marker(): void
    {
        \Illuminate\Support\Facades\Cache::put(AttemptEngine::submitMarkerKey(101, 202), 1, 60);

        $payload = $this->engine->recordFailure(101, 202, new \RuntimeException('test failure'), 'TestContext');

        $this->assertSame('error', $payload['status']);
        $this->assertSame(202, $payload['scored_module_id']);
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has(AttemptEngine::submitMarkerKey(101, 202)));
        $this->assertSame($payload, \Illuminate\Support\Facades\Cache::get('scoring_result_101'));
    }

    public function test_test_model_is_preview_predicate(): void
    {
        $preview = new \App\Models\Test(['title' => 'Test Preview']);
        $this->assertTrue($preview->isPreview());

        $regular = new \App\Models\Test(['title' => 'Official SAT Practice #1']);
        $this->assertFalse($regular->isPreview());
    }

    public function test_autosave_throws_validation_exception_on_invalid_question_id(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $attempt = new UserTest(['status' => 'in_progress', 'test_id' => 1, 'current_module_id' => 1]);
        $attempt->id = 1;
        $module = new Module();
        $module->id = 1;

        $this->engine->autosave($attempt, $module, ['not_a_number' => 'A']);
    }

    public function test_paused_clock_resumption_accumulates_prior_elapsed_time(): void
    {
        $module = new Module(['duration_minutes' => 30]);
        $attempt = new UserTest([
            'current_module_elapsed_seconds' => 600,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00'));
        $this->engine->startOrResumeModule($attempt, $module);

        $this->assertSame('2026-09-20 10:00:00', $attempt->current_module_started_at->format('Y-m-d H:i:s'));

        $timing = $this->engine->timing($attempt, $module, Carbon::parse('2026-09-20 10:05:00'));

        $this->assertSame(900, $timing['elapsed_seconds']);
        $this->assertSame(900, $timing['remaining_seconds']);
        $this->assertFalse($timing['expired']);

        Carbon::setTestNow();
    }

    public function test_autosave_returns_submitting_when_marker_set(): void
    {
        $attempt = new UserTest();
        $attempt->id = 55;
        $module = new Module();
        $module->id = 66;

        \Illuminate\Support\Facades\Cache::put(AttemptEngine::submitMarkerKey(55, 66), 1, 60);

        $result = $this->engine->autosave($attempt, $module, []);

        $this->assertSame('submitting', $result['status']);
        $this->assertFalse($result['expired']);
    }

    public function test_autosave_returns_expired_when_chained_clock_past_deadline(): void
    {
        $attempt = new UserTest([
            'assignment_id' => 1,
            'current_module_started_at' => Carbon::now()->subMinutes(40),
        ]);
        $attempt->id = 55;
        $module = new Module(['duration_minutes' => 30]);
        $module->id = 66;

        $result = $this->engine->autosave($attempt, $module, []);

        $this->assertSame('expired', $result['status']);
        $this->assertTrue($result['expired']);
    }

    public function test_autosave_paused_clock_records_client_elapsed_and_resets_started_at(): void
    {
        $past = Carbon::now()->subMinutes(10);
        $attempt = new UserTest([
            'assignment_id' => null, // paused clock
            'current_module_started_at' => $past,
            'current_module_elapsed_seconds' => 0,
        ]);
        $attempt->id = 77;
        $module = new Module(['duration_minutes' => 30]);
        $module->id = 88;

        $result = $this->engine->autosave($attempt, $module, [], [], 120);

        $this->assertSame('success', $result['status']);
        $this->assertSame(120, $attempt->current_module_elapsed_seconds);
        $this->assertTrue($attempt->current_module_started_at->greaterThan($past));
    }
}

