<?php

namespace Tests\Feature;

use App\Models\AnswerChoice;
use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\Classroom;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Services\AssignmentReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AssignmentModuleTimingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_assignment_module_deadline_survives_exit_and_reload(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [$student, $module, $attempt] = $this->assignmentAttempt(20);
        $attempt->assignment->update(['due_at' => now()->subHour()]);
        $startedAt = now()->subMinutes(5);
        $attempt->update(['current_module_started_at' => $startedAt]);

        $url = route('engine.session', $module->ulid).'?attempt='.$attempt->ulid;
        $this->actingAs($student)->get($url)
            ->assertOk()
            ->assertSee('window.isAssignmentAttempt = true', false)
            ->assertSee('window.serverRemainingSeconds = 900', false);
        $this->assertTrue($attempt->fresh()->current_module_started_at->equalTo($startedAt));

        Carbon::setTestNow('2026-06-22 12:05:00');
        $this->actingAs($student)->get($url)
            ->assertOk()
            ->assertSee('window.serverRemainingSeconds = 600', false);
        $this->assertTrue($attempt->fresh()->current_module_started_at->equalTo($startedAt));
    }

    public function test_practice_autosave_cannot_reduce_server_elapsed_time(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [$student, $module, $attempt] = $this->assignmentAttempt(20);
        $attempt->update([
            'assignment_id' => null,
            'current_module_elapsed_seconds' => 600,
            'current_module_started_at' => now()->subMinute(),
        ]);

        $this->actingAs($student)->withAttemptController($attempt)->postJson(route('engine.test.autosave-module'), [
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'answers' => [],
            'elapsed_seconds' => 60,
        ])->assertOk();

        $attempt->refresh();
        $this->assertSame(660, $attempt->current_module_elapsed_seconds);
        $this->assertTrue($attempt->current_module_started_at->equalTo(now()));
    }

    public function test_practice_client_timeout_is_rejected_before_the_server_deadline(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [$student, $module, $attempt] = $this->assignmentAttempt(20);
        $attempt->update(['assignment_id' => null, 'current_module_started_at' => now()]);

        $this->actingAs($student)->withAttemptController($attempt)->postJson(route('engine.test.submit-module'), [
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'answers' => [],
            'timed_out' => true,
        ])->assertStatus(409)
            ->assertJsonPath('error', 'module_not_expired')
            ->assertJsonPath('timing.remaining_seconds', 1200);

        $this->assertSame('in_progress', $attempt->fresh()->status);
    }

    public function test_practice_pause_resume_and_takeover_are_owned_by_one_controller(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [$student, $module, $attempt] = $this->assignmentAttempt(20);
        $attempt->update([
            'assignment_id' => null,
            'current_module_elapsed_seconds' => 120,
            'current_module_started_at' => now()->subMinute(),
        ]);
        $endpoint = route('engine.test.control');

        $claim = $this->actingAs($student)->postJson($endpoint, [
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'action' => 'claim',
        ])->assertOk()->assertJsonPath('status', 'active');
        $claimToken = $claim->json('token');

        $pause = $this->withHeader('X-Attempt-Control', $claimToken)->postJson($endpoint, [
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'action' => 'pause',
        ])->assertOk()->assertJsonPath('status', 'paused')
            ->assertJsonPath('remaining_seconds', 1020);
        $pausedToken = $pause->json('token');

        $attempt->refresh();
        $this->assertNull($attempt->current_module_started_at);
        $this->assertTrue($attempt->current_module_paused);
        $this->assertSame(180, $attempt->current_module_elapsed_seconds);

        Carbon::setTestNow(now()->addHour());
        $this->getJson(route('engine.test.timing', [
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
        ]))->assertOk()->assertJsonPath('status', 'paused')
            ->assertJsonPath('remaining_seconds', 1020);

        $this->postJson($endpoint, [
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'action' => 'claim',
        ])->assertStatus(409)->assertJsonPath('error', 'attempt_in_use');

        $resume = $this->withHeader('X-Attempt-Control', $pausedToken)->postJson($endpoint, [
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'action' => 'resume',
        ])->assertOk()->assertJsonPath('status', 'active');
        $resumedToken = $resume->json('token');
        $this->assertNotSame($pausedToken, $resumedToken);

        $takeover = $this->postJson($endpoint, [
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'action' => 'takeover',
        ])->assertOk();
        $this->assertNotSame($resumedToken, $takeover->json('token'));

        $this->withHeader('X-Attempt-Control', $resumedToken)->postJson(route('engine.test.autosave-module'), [
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'answers' => [],
        ])->assertStatus(409)->assertJsonPath('error', 'controller_lost');
    }

    public function test_reopening_paused_practice_does_not_restart_or_refund_its_clock(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [$student, $module, $attempt] = $this->assignmentAttempt(20);
        $attempt->assignment->update(['status' => 'closed']);
        $section = Section::findOrFail($module->section_id);
        $section->test->update(['is_public' => true]);
        $section->update(['is_public' => true]);
        $module->update(['is_public' => true]);
        $attempt->update([
            'assignment_id' => null,
            'attempt_number' => null,
            'current_module_started_at' => null,
            'current_module_elapsed_seconds' => 120,
            'current_module_paused' => true,
        ]);

        $url = route('engine.session', $module->ulid).'?attempt='.$attempt->ulid;
        $this->actingAs($student)->get($url)
            ->assertOk()
            ->assertSee('window.isAssignmentAttempt = false', false)
            ->assertSee('window.isPracticeClockPaused = true', false)
            ->assertSee('window.durationMinutes = 18', false);

        $this->assertNull($attempt->fresh()->current_module_started_at);
        $this->assertTrue($attempt->fresh()->current_module_paused);
        $this->assertSame(120, $attempt->fresh()->current_module_elapsed_seconds);
    }

    public function test_private_paused_clock_assignment_can_open_its_issued_module(): void
    {
        [$student, $module, $attempt] = $this->assignmentAttempt(20);
        $attempt->assignment->update(['clock_mode' => 'paused']);

        $this->actingAs($student)
            ->get(route('engine.session', $module->ulid).'?attempt='.$attempt->ulid)
            ->assertOk()
            ->assertSee('window.currentModuleId = '.$module->id, false);
    }

    public function test_assignment_autosave_uses_server_elapsed_and_rejects_late_answers(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [$student, $module, $attempt, $question] = $this->assignmentAttempt(20);
        $attempt->update(['current_module_started_at' => now()->subMinute()]);

        $this->actingAs($student)->withAttemptController($attempt)->postJson(route('engine.test.autosave-module'), [
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'answers' => [(string) $question->id => 'A'],
            'elapsed_seconds' => 999999,
        ])->assertOk();

        $this->assertSame(60, $attempt->fresh()->current_module_elapsed_seconds);
        $this->assertDatabaseHas('user_test_answers', [
            'user_test_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_answer' => 'A',
        ]);

        Carbon::setTestNow('2026-06-22 12:21:00');
        $this->actingAs($student)->withAttemptController($attempt)->postJson(route('engine.test.autosave-module'), [
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'answers' => [(string) $question->id => 'B'],
            'elapsed_seconds' => 61,
        ])->assertStatus(409)->assertJsonPath('error', 'module_expired');

        $this->assertDatabaseHas('user_test_answers', [
            'user_test_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_answer' => 'A',
        ]);
        $this->assertSame(1200, $attempt->fresh()->current_module_elapsed_seconds);
    }

    public function test_teacher_report_uses_live_server_elapsed_time(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [$student, $module, $attempt] = $this->assignmentAttempt(20);
        $attempt->update([
            'current_module_started_at' => now()->subMinutes(5),
            'current_module_elapsed_seconds' => 10,
        ]);
        $recipient = AssignmentRecipient::create([
            'assignment_id' => $attempt->assignment_id,
            'student_id' => $student->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $row = app(AssignmentReportService::class)->buildRecipient($attempt->assignment, $recipient);

        $this->assertSame(300, $row['in_progress']->current_module_elapsed_seconds);
        $this->assertSame($module->id, $row['in_progress']->current_module_id);
    }

    public function test_expired_assignment_submission_scores_only_previously_saved_answers(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [$student, $module, $attempt, $question] = $this->assignmentAttempt(1);
        $attempt->update(['current_module_started_at' => now()->subMinutes(2)]);
        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $question->id,
            'selected_answer' => 'A',
            'is_correct' => true,
        ]);

        $this->actingAs($student)->withAttemptController($attempt)->postJson(route('engine.test.submit-module'), [
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'answers' => [(string) $question->id => 'B'],
        ])->assertOk()->assertJsonPath('timed_out', true);

        $this->assertDatabaseHas('user_test_answers', [
            'user_test_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_answer' => 'A',
        ]);
        $this->assertSame('completed', $attempt->fresh()->status);
    }

    /** @return array{0: User, 1: Module, 2: UserTest, 3: Question} */
    private function assignmentAttempt(int $durationMinutes): array
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $test = Test::create([
            'title' => 'Timed Assignment Test',
            'test_type' => 'module_only',
            'status' => 'active',
            'created_by' => $teacher->id,
            'is_public' => false,
        ]);
        $section = Section::create([
            'test_id' => $test->id,
            'name' => 'Math',
            'type' => Section::TYPE_MATH,
            'order' => 1,
            'created_by' => $teacher->id,
        ]);
        $module = Module::create([
            'section_id' => $section->id,
            'module_number' => 2,
            'difficulty_level' => Module::DIFFICULTY_STANDARD,
            'duration_minutes' => $durationMinutes,
            'total_questions' => 1,
            'order' => 1,
            'created_by' => $teacher->id,
        ]);
        $question = Question::create([
            'stem' => 'Choose A.',
            'question_type' => Question::TYPE_MCQ,
            'difficulty' => 'easy',
            'section_type' => Section::TYPE_MATH,
            'skill_domain' => 'algebra',
            'is_complete' => true,
            'created_by' => $teacher->id,
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

        $classroom = Classroom::create(['owner_id' => $teacher->id, 'name' => 'Timed Class']);
        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Timed Work',
            'status' => 'published',
            'attempt_limit' => 1,
        ]);
        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'assignment_id' => $assignment->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
            'current_module_id' => $module->id,
            'current_module_started_at' => now(),
        ]);

        return [$student, $module, $attempt, $question];
    }
}
