<?php

namespace Tests\Feature;

use App\Jobs\ScoreModuleJob;
use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Models\UserTestModuleSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A 70-student mock exam produced two popups — `Scoring timeout` and
 * `module_progression_conflict` — from one underlying condition: the scoring
 * queue could not drain in time, and the system reported "not finished yet" as
 * "failed". These tests lock in the server half of the fix.
 *
 * @see ModuleProgressionSecurityTest for the conflict cases that MUST still 409.
 */
class SubmitStatusResilienceTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Test $test;

    private Module $moduleOne;

    private Module $easyModule;

    private Question $moduleOneQuestion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create(['email_verified_at' => now()]);
        $this->test = Test::create([
            'title' => 'Submit Resilience Test',
            'test_type' => 'section_only',
            'status' => 'active',
            'is_public' => true,
        ]);
        $section = Section::create([
            'test_id' => $this->test->id,
            'name' => 'Math',
            'type' => Section::TYPE_MATH,
            'order' => 1,
            'is_public' => true,
        ]);

        $this->moduleOne = $this->createModule($section, 1, Module::DIFFICULTY_STANDARD, 1);
        $this->easyModule = $this->createModule($section, 2, Module::DIFFICULTY_EASY, 2);
        $hardModule = $this->createModule($section, 2, Module::DIFFICULTY_HARD, 3);
        $this->moduleOneQuestion = $this->attachQuestion($this->moduleOne, 'Module one question');
        $this->attachQuestion($this->easyModule, 'Easy module question');
        // Both branches need a question or TestStructureService::validate() rejects
        // the whole form, which would make inline scoring fall back to the queue
        // for a reason that has nothing to do with what these tests assert.
        $this->attachQuestion($hardModule, 'Hard module question');
    }

    /**
     * A held lock means this exact attempt+module is already being scored. The old
     * code answered `module_progression_conflict`, which students read as a
     * failure and retried — growing the queue. It must now say "still working"
     * with a distinct code the client treats as "keep polling, never re-POST".
     */
    public function test_submit_while_lock_is_held_reports_in_progress_not_conflict(): void
    {
        $attempt = $this->attempt();
        $lock = Cache::lock("module_submit_lock_{$attempt->id}_{$this->moduleOne->id}", 60);
        $this->assertTrue($lock->get(), 'Precondition: the test must own the lock.');

        try {
            $response = $this->actingAs($this->student)
                ->postJson(route('engine.test.submit-module'), [
                    'user_test_id' => $attempt->id,
                    'module_id' => $this->moduleOne->id,
                    'answers' => [(string) $this->moduleOneQuestion->id => 'A'],
                ])
                ->assertStatus(409)
                ->assertJsonPath('error', 'submission_in_progress');

            $this->assertSame('2', $response->headers->get('Retry-After'));

            // The blocked request must be inert: no answers written, no progression.
            $this->assertSame(0, UserTestAnswer::where('user_test_id', $attempt->id)->count());
            $this->assertSame(
                $this->moduleOne->id,
                $attempt->fresh()->current_module_id,
                'A blocked submit must not advance the attempt.'
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * Guards the regression risk of the change above: a genuinely wrong module
     * must still be rejected as a progression conflict, even while some other
     * module happens to hold its own lock.
     */
    public function test_wrong_module_still_conflicts_while_another_modules_lock_is_held(): void
    {
        $attempt = $this->attempt();
        $otherLock = Cache::lock("module_submit_lock_{$attempt->id}_{$this->easyModule->id}", 60);
        $this->assertTrue($otherLock->get());

        try {
            $this->actingAs($this->student)
                ->postJson(route('engine.test.submit-module'), [
                    'user_test_id' => $attempt->id,
                    'module_id' => $this->easyModule->id,
                    'answers' => [],
                ])
                ->assertStatus(409)
                ->assertJsonPath('error', 'submission_in_progress');

            // Module one is current but uncontended: this is a real conflict.
            $attempt->forceFill(['current_module_id' => $this->easyModule->id])->save();

            $this->actingAs($this->student)
                ->postJson(route('engine.test.submit-module'), [
                    'user_test_id' => $attempt->id,
                    'module_id' => $this->moduleOne->id,
                    'answers' => [],
                ])
                ->assertStatus(409)
                ->assertJsonPath('error', 'module_progression_conflict');
        } finally {
            $otherLock->release();
        }
    }

    /**
     * The cache is not durable: a job can commit its receipt and then die before
     * Cache::put, or a racing submit can forget the key. Without a fallback the
     * client polls until its budget runs out for work that is actually finished.
     */
    public function test_status_falls_back_to_the_receipt_when_the_cache_is_empty(): void
    {
        $attempt = $this->attempt();
        Cache::forget("scoring_result_{$attempt->id}");

        UserTestModuleSubmission::create([
            'user_test_id' => $attempt->id,
            'module_id' => $this->moduleOne->id,
            'issued_next_module_id' => $this->easyModule->id,
            'result' => [
                'status' => 'success',
                'next_module_id' => $this->easyModule->ulid,
                'scored_module_id' => $this->moduleOne->id,
            ],
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->getJson(route('engine.submit-status', $attempt).'?module_id='.$this->moduleOne->id)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('next_module_id', $this->easyModule->ulid);
    }

    /**
     * The result cache key is per-ATTEMPT, so a client waiting on module A must
     * never be handed module B's result — that routes it backwards into a module
     * it already finished. The longer client budget widens this window.
     */
    public function test_status_ignores_a_result_belonging_to_a_different_module(): void
    {
        $attempt = $this->attempt();

        Cache::put("scoring_result_{$attempt->id}", [
            'status' => 'success',
            'next_module_id' => $this->easyModule->ulid,
            'scored_module_id' => $this->easyModule->id,
        ], 300);

        $this->actingAs($this->student)
            ->getJson(route('engine.submit-status', $attempt).'?module_id='.$this->moduleOne->id)
            ->assertOk()
            ->assertJsonPath('status', 'scoring')
            ->assertJsonMissing(['next_module_id' => $this->easyModule->ulid]);
    }

    /**
     * Backward compatibility for the deploy window. Results cached before this
     * change carry no `scored_module_id`; rejecting them would strand every
     * in-flight client for the whole result TTL.
     */
    public function test_status_still_serves_a_legacy_result_without_scored_module_id(): void
    {
        $attempt = $this->attempt();

        Cache::put("scoring_result_{$attempt->id}", [
            'status' => 'success',
            'next_module_id' => $this->easyModule->ulid,
        ], 300);

        $this->actingAs($this->student)
            ->getJson(route('engine.submit-status', $attempt).'?module_id='.$this->moduleOne->id)
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    /**
     * Older clients poll without the parameter; behaviour must be unchanged.
     */
    public function test_status_without_module_id_behaves_as_before(): void
    {
        $attempt = $this->attempt();

        Cache::put("scoring_result_{$attempt->id}", [
            'status' => 'success',
            'next_module_id' => $this->easyModule->ulid,
            'scored_module_id' => $this->easyModule->id,
        ], 300);

        $this->actingAs($this->student)
            ->getJson(route('engine.submit-status', $attempt))
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    /**
     * Nothing cached and nothing recorded is the only case that should keep the
     * client waiting.
     */
    public function test_status_reports_scoring_when_there_is_no_result_at_all(): void
    {
        $attempt = $this->attempt();
        Cache::forget("scoring_result_{$attempt->id}");

        $this->actingAs($this->student)
            ->getJson(route('engine.submit-status', $attempt).'?module_id='.$this->moduleOne->id)
            ->assertOk()
            ->assertJsonPath('status', 'scoring');
    }

    /**
     * The timing set is interlocking; a change to one number that breaks an
     * invariant reintroduces either duplicate job execution (I1) or duplicate
     * dispatch (I3). See config/scoring.php.
     */
    public function test_scoring_timing_invariants_hold(): void
    {
        $jobTimeout = (int) config('scoring.job_timeout');
        $lockTtl = (int) config('scoring.lock_ttl');
        $clientBudget = (int) config('scoring.client_budget');
        $resultTtl = (int) config('scoring.result_ttl');
        $retryAfter = (int) config('queue.connections.database.retry_after');

        $this->assertGreaterThan($jobTimeout, $retryAfter, 'I1: retry_after must exceed the job timeout.');
        $this->assertGreaterThan($jobTimeout, $lockTtl, 'I2: the lock must outlive a running job.');
        $this->assertGreaterThan($lockTtl, $clientBudget, 'I3: client budget must strictly exceed the lock TTL.');
        $this->assertGreaterThan($clientBudget, $resultTtl, 'I4: results must outlive the client budget.');
    }

    /**
     * The whole point of inline scoring: a non-terminal module must NOT touch the
     * queue, so a cron-driven worker's cold start stops applying to routing.
     */
    public function test_non_terminal_module_is_scored_inline_without_touching_the_queue(): void
    {
        Queue::fake();
        $attempt = $this->attempt();

        $this->actingAs($this->student)
            ->postJson(route('engine.test.submit-module'), [
                'user_test_id' => $attempt->id,
                'module_id' => $this->moduleOne->id,
                'answers' => [(string) $this->moduleOneQuestion->id => 'A'],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonMissing(['status' => 'scoring']);

        Queue::assertNotPushed(ScoreModuleJob::class);

        // Inline must leave exactly the state the job would have: attempt advanced,
        // receipt written. ModuleProgressionSecurityTest's idempotency assertion
        // depends on that receipt being identical.
        $attempt->refresh();
        $this->assertNotSame($this->moduleOne->id, $attempt->current_module_id);
        $this->assertSame(1, UserTestModuleSubmission::where('user_test_id', $attempt->id)->count());
    }

    /**
     * The kill switch has to actually work — it is the only way to disable inline
     * scoring mid-exam without a deploy.
     */
    public function test_inline_scoring_kill_switch_falls_back_to_the_queue(): void
    {
        config(['scoring.inline_non_final' => false]);
        Queue::fake();
        $attempt = $this->attempt();

        $this->actingAs($this->student)
            ->postJson(route('engine.test.submit-module'), [
                'user_test_id' => $attempt->id,
                'module_id' => $this->moduleOne->id,
                'answers' => [(string) $this->moduleOneQuestion->id => 'A'],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'scoring');

        Queue::assertPushed(ScoreModuleJob::class);
    }

    /**
     * Terminal submissions run finalize() — two IRT estimates plus conversion plus
     * a possible auto-merge — and must stay off the request thread.
     */
    public function test_terminal_module_stays_on_the_queue(): void
    {
        $attempt = $this->attempt();

        // Advance to module 2 (terminal for this single-section fixture).
        $this->actingAs($this->student)
            ->postJson(route('engine.test.submit-module'), [
                'user_test_id' => $attempt->id,
                'module_id' => $this->moduleOne->id,
                'answers' => [(string) $this->moduleOneQuestion->id => 'A'],
            ])->assertOk();

        $issued = Module::whereKey($attempt->fresh()->current_module_id)->firstOrFail();

        Queue::fake();
        $this->actingAs($this->student)
            ->postJson(route('engine.test.submit-module'), [
                'user_test_id' => $attempt->id,
                'module_id' => $issued->id,
                'answers' => [(string) $issued->questions()->firstOrFail()->id => 'A'],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'scoring');

        Queue::assertPushed(ScoreModuleJob::class);
    }

    public function test_is_terminal_submission_distinguishes_routing_from_finalize(): void
    {
        $attempt = $this->attempt();
        $progression = app(\App\Services\TestProgressionService::class);

        $this->assertFalse(
            $progression->isTerminalSubmission($attempt, $this->moduleOne),
            'Adaptive module 1 always routes onward, so it is never terminal.'
        );

        $this->assertTrue(
            $progression->isTerminalSubmission($attempt, $this->easyModule),
            'Module 2 of the only section ends the attempt.'
        );
    }

    private function attempt(): UserTest
    {
        return UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'in_progress',
            'current_module_id' => $this->moduleOne->id,
        ]);
    }

    private function createModule(Section $section, int $number, string $difficulty, int $order): Module
    {
        return Module::create([
            'section_id' => $section->id,
            'module_number' => $number,
            'difficulty_level' => $difficulty,
            'duration_minutes' => 20,
            'total_questions' => 1,
            'order' => $order,
            'is_public' => true,
        ]);
    }

    private function attachQuestion(Module $module, string $stem): Question
    {
        $question = Question::create([
            'stem' => $stem,
            'question_type' => Question::TYPE_MCQ,
            'difficulty' => 'medium',
            'section_type' => Section::TYPE_MATH,
            'skill_domain' => 'algebra',
            'is_complete' => true,
        ]);

        foreach (['A', 'B', 'C', 'D'] as $index => $label) {
            AnswerChoice::create([
                'question_id' => $question->id,
                'label' => $label,
                'content' => $label,
                'is_correct' => $index === 0,
                'order' => $index + 1,
            ]);
        }

        $module->questions()->attach($question->id, ['position' => 1]);

        return $question;
    }
}
