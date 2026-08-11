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
use App\Models\UserTestModuleSubmission;
use App\Services\AssignmentAttemptTimeoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * An assignment is a fixed-time exam, so its clock must keep running after the
 * student closes the tab. These lock in the server-side cascade that replaced the
 * browser-only auto-submit.
 */
class AssignmentTimeoutCascadeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sweeper_cascades_through_every_expired_module_and_completes_the_attempt(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [, $modules, $attempt] = $this->assignmentAttempt(moduleCount: 2);
        // Left during module 1 and never came back.
        $attempt->update(['current_module_started_at' => now()->subHours(3)]);

        $this->artisan('assignments:finalize-expired')->assertSuccessful();

        $attempt->refresh();
        $this->assertSame('completed', $attempt->status);
        $this->assertNull($attempt->current_module_id);

        foreach ($modules as $module) {
            $this->assertDatabaseHas('user_test_module_submissions', [
                'user_test_id' => $attempt->id,
                'module_id' => $module->id,
            ]);
        }
    }

    public function test_cascade_materializes_unanswered_questions_as_omitted_and_keeps_autosaved_work(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [, $modules, $attempt, $questions] = $this->assignmentAttempt(moduleCount: 2);
        $attempt->update(['current_module_started_at' => now()->subHours(3)]);

        // Autosaved before the student vanished — must survive the sweep.
        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $modules[0]->id,
            'question_id' => $questions[0]->id,
            'selected_answer' => 'A',
            'is_correct' => true,
        ]);

        app(AssignmentAttemptTimeoutService::class)->finalizeExpired($attempt);

        $this->assertDatabaseHas('user_test_answers', [
            'user_test_id' => $attempt->id,
            'question_id' => $questions[0]->id,
            'selected_answer' => 'A',
        ]);
        $this->assertDatabaseHas('user_test_answers', [
            'user_test_id' => $attempt->id,
            'question_id' => $questions[1]->id,
            'selected_answer' => null,
            'is_correct' => false,
        ]);
    }

    public function test_next_module_clock_starts_at_the_previous_deadline_not_at_sweep_time(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [, $modules, $attempt] = $this->assignmentAttempt(moduleCount: 3);
        // Module 1 (10 min) expired 5 minutes ago; module 2 should still be live.
        $startedAt = now()->subMinutes(15);
        $attempt->update(['current_module_started_at' => $startedAt]);

        // Sweep runs a further 4 minutes late, as a once-a-minute cron under load
        // eventually will. The boundary must not move with it.
        Carbon::setTestNow('2026-06-22 12:04:00');
        $submitted = app(AssignmentAttemptTimeoutService::class)->finalizeExpired($attempt);

        $attempt->refresh();
        $this->assertSame(1, $submitted);
        $this->assertSame('in_progress', $attempt->status);
        $this->assertSame((int) $modules[1]->id, (int) $attempt->current_module_id);
        $this->assertTrue(
            $attempt->current_module_started_at->equalTo($startedAt->copy()->addMinutes(10)),
            'Module 2 must start at module 1\'s deadline, not when the sweeper happened to run.'
        );
    }

    public function test_sweeper_backs_off_when_a_live_submission_holds_the_lock(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [, $modules, $attempt] = $this->assignmentAttempt(moduleCount: 2);
        $attempt->update(['current_module_started_at' => now()->subHours(3)]);

        $lock = Cache::lock("module_submit_lock_{$attempt->id}_{$modules[0]->id}", 60);
        $this->assertTrue($lock->get());

        try {
            $submitted = app(AssignmentAttemptTimeoutService::class)->finalizeExpired($attempt);
        } finally {
            $lock->release();
        }

        $this->assertSame(0, $submitted);
        $this->assertSame('in_progress', $attempt->fresh()->status);
        $this->assertSame(0, UserTestModuleSubmission::where('user_test_id', $attempt->id)->count());
    }

    public function test_practice_attempts_are_never_swept(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [, , $attempt] = $this->assignmentAttempt(moduleCount: 2);
        $attempt->update([
            'assignment_id' => null,
            'attempt_number' => null,
            'current_module_started_at' => now()->subHours(3),
        ]);

        $this->artisan('assignments:finalize-expired')->assertSuccessful();

        $this->assertSame('in_progress', $attempt->fresh()->status);
        $this->assertSame(0, UserTestModuleSubmission::where('user_test_id', $attempt->id)->count());
    }

    public function test_returning_student_is_finalized_and_sent_to_their_score(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [$student, $modules, $attempt] = $this->assignmentAttempt(moduleCount: 2);
        $attempt->update(['current_module_started_at' => now()->subHours(3)]);

        $this->actingAs($student)
            ->get(route('engine.session', $modules[0]->ulid).'?attempt='.$attempt->ulid)
            ->assertRedirect(route('student.scores.show', $attempt));

        $this->assertSame('completed', $attempt->fresh()->status);
    }

    /**
     * A linear custom test with $moduleCount modules of 10 minutes each, assigned
     * to one student, with an attempt parked on the first module.
     *
     * @return array{0: User, 1: array<int, Module>, 2: UserTest, 3: array<int, Question>}
     */
    private function assignmentAttempt(int $moduleCount): array
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create(['email_verified_at' => now()]);

        $test = Test::create([
            'title' => 'Cascade Test',
            'test_type' => 'custom_test',
            'status' => 'active',
            'created_by' => $teacher->id,
            'is_public' => false,
        ]);
        $section = Section::create([
            'test_id' => $test->id,
            'name' => 'Reading and Writing',
            'type' => Section::TYPE_RW,
            'order' => 1,
            'created_by' => $teacher->id,
        ]);

        $modules = [];
        $questions = [];
        for ($number = 1; $number <= $moduleCount; $number++) {
            $module = Module::create([
                'section_id' => $section->id,
                'module_number' => $number,
                'difficulty_level' => Module::DIFFICULTY_STANDARD,
                'duration_minutes' => 10,
                'total_questions' => 2,
                'order' => $number,
                'created_by' => $teacher->id,
            ]);

            for ($position = 1; $position <= 2; $position++) {
                $question = Question::create([
                    'stem' => 'Choose A.',
                    'question_type' => Question::TYPE_MCQ,
                    'difficulty' => 'easy',
                    'section_type' => Section::TYPE_RW,
                    'skill_domain' => 'information_and_ideas',
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
                $module->questions()->attach($question->id, ['position' => $position]);
                $questions[] = $question;
            }

            $modules[] = $module;
        }

        $classroom = Classroom::create(['owner_id' => $teacher->id, 'name' => 'Cascade Class']);
        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Timed Work',
            'status' => 'published',
            'attempt_limit' => 1,
        ]);
        AssignmentRecipient::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'assignment_id' => $assignment->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
            'attempt_type' => 'full',
            'current_module_id' => $modules[0]->id,
            'current_module_started_at' => now(),
        ]);

        return [$student, $modules, $attempt, $questions];
    }
}
