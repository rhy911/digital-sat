<?php

namespace Tests\Feature;

use App\Models\AnswerChoice;
use App\Models\ExamSession;
use App\Models\Module;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Livewire\Teacher\ExamAttemptMonitor;
use App\Services\AssignmentAttemptTimeoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_creating_a_session_generates_a_unique_six_character_code(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);

        $this->actingAs($teacher)->post(route('teacher.exam-sessions.store'), [
            'test_id' => $test->id,
            'title' => 'Mock Exam',
        ])->assertRedirect();

        $session = ExamSession::first();
        $this->assertNotNull($session);
        $this->assertSame(6, strlen($session->code));
        $this->assertSame('active', $session->status);
    }

    public function test_index_renders_the_session_list_and_create_form(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $session = ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Listed Mock Exam',
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.exam-sessions.index'))
            ->assertOk()
            ->assertSee('Listed Mock Exam')
            ->assertSee($session->code)
            ->assertSee('Start a new session');
    }

    public function test_index_shows_an_empty_state_when_there_are_no_sessions(): void
    {
        $teacher = $this->approvedTeacher();

        $this->actingAs($teacher)
            ->get(route('teacher.exam-sessions.index'))
            ->assertOk()
            ->assertSee('No sessions yet');
    }

    public function test_code_entry_screen_is_reachable_signed_out_and_signed_in(): void
    {
        // The back link points at whichever surface the visitor could have come
        // from, so it is asserted per auth state rather than as one string.
        $this->get(route('exam-join.entry'))
            ->assertOk()
            ->assertSee('Join an exam')
            ->assertSee(route('landing'), false);

        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $this->actingAs($student)
            ->get(route('exam-join.entry'))
            ->assertOk()
            ->assertSee('Join an exam')
            ->assertSee('Back to home');
    }

    public function test_typed_code_redirects_to_the_join_screen(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $session = ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Typed Code Exam',
        ]);

        // Lowercased on purpose: codes get read aloud, so the entry point must
        // not care how the candidate typed it.
        $this->post(route('exam-join.entry.submit'), ['code' => strtolower($session->code)])
            ->assertRedirect(route('exam-join.show', $session->code));
    }

    public function test_unknown_code_returns_to_the_form_with_an_error(): void
    {
        $this->from(route('exam-join.entry'))
            ->post(route('exam-join.entry.submit'), ['code' => 'ZZZZZZ'])
            ->assertRedirect(route('exam-join.entry'))
            ->assertSessionHasErrors('code');
    }

    public function test_paused_session_code_explains_itself_rather_than_404ing(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $session = ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Paused Exam',
            'status' => 'paused',
        ]);

        $this->from(route('exam-join.entry'))
            ->post(route('exam-join.entry.submit'), ['code' => $session->code])
            ->assertRedirect(route('exam-join.entry'))
            ->assertSessionHasErrors('code');
    }

    public function test_signed_in_student_can_join_an_exam_session_by_code(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $session = ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Student Join Exam',
        ]);
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)
            ->post(route('exam-join.process', $session->code))
            ->assertRedirect();

        $attempt = UserTest::where('exam_session_id', $session->id)->first();
        $this->assertNotNull($attempt);
        $this->assertSame($student->id, $attempt->user_id);
        // A real account keeps its own name; guest_name is only for throwaway users.
        $this->assertNull($attempt->guest_name);
    }

    public function test_private_test_blocks_an_unrelated_student_without_exam_session(): void
    {
        // Proves the fixture used below is actually private, and that the guest
        // path in the next test is what makes access work — not a fluke of the
        // test data being public.
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $module = $test->sections()->first()->modules()->first();

        $stranger = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $strangerAttempt = UserTest::create([
            'user_id' => $stranger->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
            'current_module_id' => $module->id,
        ]);

        $this->actingAs($stranger)
            ->get(route('engine.session', ['ulid' => $module->ulid, 'attempt' => $strangerAttempt->ulid]))
            ->assertNotFound();
    }

    public function test_guest_can_join_a_private_test_via_code_and_reach_the_engine(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $session = ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Mock Exam',
        ]);

        $response = $this->post(route('exam-join.process', $session->code), [
            'display_name' => 'Guest Candidate',
        ]);

        $response->assertRedirect();
        $guest = User::where('is_guest', true)->first();
        $this->assertNotNull($guest);
        $this->assertSame('student', $guest->role);
        $this->assertNotNull($guest->email_verified_at);

        $attempt = UserTest::where('exam_session_id', $session->id)->first();
        $this->assertNotNull($attempt);
        $this->assertSame('Guest Candidate', $attempt->guest_name);
        $this->assertSame('in_progress', $attempt->status);

        $this->actingAs($guest)
            ->get($response->headers->get('Location'))
            ->assertOk();
    }

    public function test_paused_session_rejects_new_joins(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $session = ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Mock Exam',
            'status' => 'paused',
        ]);

        $this->post(route('exam-join.process', $session->code), [
            'display_name' => 'Guest Candidate',
        ])->assertStatus(409);
    }

    public function test_teacher_cannot_view_another_teachers_session(): void
    {
        $owner = $this->approvedTeacher();
        $other = $this->approvedTeacher();
        $test = $this->privateTeacherTest($owner);
        $session = ExamSession::create([
            'teacher_id' => $owner->id,
            'test_id' => $test->id,
            'title' => 'Mock Exam',
        ]);

        $this->actingAs($other)
            ->get(route('teacher.exam-sessions.show', $session))
            ->assertForbidden();
    }

    public function test_guest_does_not_see_the_exit_exam_control(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $session = ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Mock Exam',
        ]);

        $response = $this->post(route('exam-join.process', $session->code), [
            'display_name' => 'Guest Candidate',
        ]);

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertDontSee('Exit the exam');
    }

    public function test_regular_student_still_sees_the_exit_exam_control(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $test = Test::create([
            'title' => 'Public Practice Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'active',
            'is_public' => true,
        ]);
        $section = Section::create([
            'test_id' => $test->id,
            'name' => 'Reading and Writing',
            'type' => Section::TYPE_RW,
            'order' => 1,
            'is_public' => true,
        ]);
        $module = Module::create([
            'section_id' => $section->id,
            'key' => 'REGULAR_EXIT_TEST_'.$test->id.'_RW_M1',
            'module_number' => 1,
            'difficulty_level' => Module::DIFFICULTY_STANDARD,
            'duration_minutes' => 32,
            'total_questions' => 1,
            'order' => 1,
            'is_public' => true,
        ]);
        $module->sections()->syncWithoutDetaching([$section->id]);
        $question = Question::create([
            'stem' => 'Regular exit test question.',
            'question_type' => Question::TYPE_MCQ,
            'difficulty' => 'easy',
            'is_pretest' => false,
            'is_complete' => true,
            'section_type' => Section::TYPE_RW,
            'skill_domain' => 'information_and_ideas',
        ]);
        foreach (['A', 'B', 'C', 'D'] as $index => $label) {
            AnswerChoice::create([
                'question_id' => $question->id,
                'label' => $label,
                'content' => "Choice {$label}",
                'is_correct' => $index === 0,
                'order' => $index + 1,
            ]);
        }
        $module->questions()->attach($question->id, ['position' => 1]);

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
            'current_module_id' => $module->id,
        ]);

        $this->actingAs($student)
            ->get(route('engine.session', ['ulid' => $module->ulid, 'attempt' => $attempt->ulid]))
            ->assertOk()
            ->assertSee('Exit the exam', false);
    }

    public function test_completed_guest_attempt_shows_a_minimal_result_with_sign_up_prompt(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $guest = $this->makeGuest();
        $session = ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Mock Exam',
        ]);
        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'exam_session_id' => $session->id,
            'guest_name' => 'Guest',
            'status' => 'completed',
            'total_score' => 1200,
            'score_reading_writing' => 600,
            'score_math' => 600,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($guest)->get(route('exam-join.result', $attempt));

        $response->assertOk();
        $response->assertSee('1200');
        $response->assertSee('This result is not saved yet', false);
        $response->assertSee(route('exam-join.link-account', ['destination' => 'signup']), false);
    }

    public function test_guest_cannot_view_another_guests_result(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $owner = $this->makeGuest(['name' => 'Owner Guest']);
        $intruder = $this->makeGuest(['name' => 'Intruder Guest']);
        $attempt = UserTest::create([
            'user_id' => $owner->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'total_score' => 1000,
            'completed_at' => now(),
        ]);

        $this->actingAs($intruder)
            ->get(route('exam-join.result', $attempt))
            ->assertForbidden();
    }

    public function test_signup_after_guest_exam_merges_the_attempt_into_the_new_account(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $attempt = $this->completedGuestAttempt($teacher, $test);
        $guest = $attempt->user;

        $this->actingAs($guest)
            ->get(route('exam-join.link-account', ['destination' => 'signup']))
            ->assertRedirect(route('signup', ['role' => 'student']));

        $this->assertGuest();
        $this->assertSame($guest->id, session('link_guest_user_id'));

        $this->post(route('signup'), [
            'username' => 'newrealstudent',
            'email' => 'newrealstudent@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'student',
        ])->assertRedirect(route('verify.email.notice'));

        $realUser = User::where('email', 'newrealstudent@example.com')->firstOrFail();

        $this->assertSame($realUser->id, $attempt->fresh()->user_id);
        $this->assertTrue($guest->fresh()->trashed());
        $this->assertNull(session('link_guest_user_id'));
    }

    public function test_signin_after_guest_exam_merges_the_attempt_into_the_existing_account(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $attempt = $this->completedGuestAttempt($teacher, $test);
        $guest = $attempt->user;

        $realUser = User::factory()->create([
            'role' => 'student',
            'email' => 'existing@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($guest)
            ->get(route('exam-join.link-account', ['destination' => 'signin']));

        $this->assertGuest();

        $this->post(route('signin'), [
            'email' => 'existing@example.com',
            'password' => 'password123',
            'role' => 'student',
        ])->assertRedirect();

        $this->assertSame($realUser->id, $attempt->fresh()->user_id);
        $this->assertTrue($guest->fresh()->trashed());
    }

    public function test_merge_leaves_a_colliding_in_progress_attempt_with_the_guest(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);

        $realUser = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        UserTest::create([
            'user_id' => $realUser->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
        ]);

        $guest = $this->makeGuest();
        $guestAttempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
        ]);

        $merged = app(\App\Services\ExamSessionService::class)->mergeGuestAccount($guest->id, $realUser);

        $this->assertSame(0, $merged);
        $this->assertSame($guest->id, $guestAttempt->fresh()->user_id);
        $this->assertTrue($guest->fresh()->trashed());
    }

    private function completedGuestAttempt(User $teacher, Test $test): UserTest
    {
        $guest = $this->makeGuest();
        $session = ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Mock Exam',
        ]);

        return UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'exam_session_id' => $session->id,
            'guest_name' => 'Guest',
            'status' => 'completed',
            'total_score' => 1100,
            'completed_at' => now(),
        ]);
    }

    public function test_exam_session_attempt_runs_on_the_chained_clock(): void
    {
        [, , $attempt] = $this->examSessionAttempt(moduleCount: 1);

        $this->assertTrue($attempt->runsOnChainedClock());
    }

    public function test_guest_engine_page_disables_client_side_pause(): void
    {
        [$guest, $modules, $attempt] = $this->examSessionAttempt(moduleCount: 1);

        $this->actingAs($guest)
            ->get(route('engine.session', ['ulid' => $modules[0]->ulid, 'attempt' => $attempt->ulid]))
            ->assertOk()
            ->assertSee('window.isAssignmentAttempt = true;', false);
    }

    public function test_sweeper_cascades_an_expired_exam_session_attempt_to_completion(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [, $modules, $attempt] = $this->examSessionAttempt(moduleCount: 2);
        // Guest closed the tab during module 1 and never came back.
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

    public function test_next_module_clock_for_exam_session_chains_from_the_prior_deadline(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        [, $modules, $attempt] = $this->examSessionAttempt(moduleCount: 3);
        $startedAt = now()->subMinutes(15);
        $attempt->update(['current_module_started_at' => $startedAt]);

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

    /**
     * A linear custom test with $moduleCount modules of 10 minutes each, joined
     * via a guest exam session, with the attempt parked on the first module.
     * Mirrors AssignmentTimeoutCascadeTest::assignmentAttempt() so the same
     * sweeper behavior can be proven for exam-session attempts.
     *
     * @return array{0: User, 1: array<int, Module>, 2: UserTest}
     */
    private function examSessionAttempt(int $moduleCount): array
    {
        $teacher = $this->approvedTeacher();
        $guest = $this->makeGuest();

        $test = Test::create([
            'title' => 'Chained Clock Exam',
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
        for ($number = 1; $number <= $moduleCount; $number++) {
            $module = Module::create([
                'section_id' => $section->id,
                'module_number' => $number,
                'difficulty_level' => Module::DIFFICULTY_STANDARD,
                'duration_minutes' => 10,
                'total_questions' => 1,
                'order' => $number,
                'created_by' => $teacher->id,
            ]);

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
            $module->questions()->attach($question->id, ['position' => 1]);

            $modules[] = $module;
        }

        $session = ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Chained Clock Session',
        ]);

        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'exam_session_id' => $session->id,
            'guest_name' => 'Guest',
            'status' => 'in_progress',
            'attempt_type' => 'full',
            'current_module_id' => $modules[0]->id,
            'current_module_started_at' => now(),
        ]);

        return [$guest, $modules, $attempt];
    }

    public function test_roster_shows_percentage_progress_while_an_attempt_is_in_progress(): void
    {
        // Fixture is 4 modules x 1 question, so one answer is 25% — proves the
        // denominator comes from the whole test, not just the current module.
        [, $modules, $attempt] = $this->examSessionAttempt(moduleCount: 4);
        $session = $attempt->examSession;

        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $modules[0]->id,
            'question_id' => $modules[0]->questions()->first()->id,
            'selected_answer' => 'A',
            'is_correct' => true,
        ]);

        $this->actingAs($session->teacher)
            ->get(route('teacher.exam-sessions.show', $session))
            ->assertOk()
            ->assertSee('Progress')
            ->assertSee('25%')
            ->assertSee('1 / 4')
            ->assertDontSee('exam-score--total', false);
    }

    public function test_progress_denominator_ignores_the_unrouted_adaptive_module_two(): void
    {
        // A candidate sits exactly one Module 2 path, so counting both the easy
        // and hard variants would inflate the denominator and cap progress
        // below 100% forever.
        [, $modules, $attempt] = $this->examSessionAttempt(moduleCount: 2);
        $session = $attempt->examSession;

        $sectionId = $modules[1]->section_id;
        $altPath = Module::create([
            'section_id' => $sectionId,
            'module_number' => $modules[1]->module_number,
            'difficulty_level' => Module::DIFFICULTY_HARD,
            'duration_minutes' => 10,
            'total_questions' => 1,
            'order' => 3,
            'created_by' => $session->teacher_id,
        ]);
        $altPath->sections()->syncWithoutDetaching([$sectionId]);

        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $modules[0]->id,
            'question_id' => $modules[0]->questions()->first()->id,
            'selected_answer' => 'A',
            'is_correct' => true,
        ]);

        $this->actingAs($session->teacher)
            ->get(route('teacher.exam-sessions.show', $session))
            ->assertOk()
            // 1 of 2, not 1 of 3.
            ->assertSee('1 / 2')
            ->assertSee('50%');
    }

    public function test_roster_shows_scores_once_the_attempt_is_complete(): void
    {
        [, , $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;
        $attempt->update([
            'status' => 'completed',
            'completed_at' => now(),
            'total_score' => 1180,
            'score_reading_writing' => 590,
            'score_math' => 590,
        ]);

        $this->actingAs($session->teacher)
            ->get(route('teacher.exam-sessions.show', $session))
            ->assertOk()
            ->assertSee('1180')
            ->assertSee('exam-score--total', false);
    }

    public function test_roster_actions_live_behind_a_single_menu(): void
    {
        [, , $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;

        $response = $this->actingAs($session->teacher)
            ->get(route('teacher.exam-sessions.show', $session))
            ->assertOk();

        $response->assertSee('Actions');
        $response->assertSee('Force submit');
        $response->assertSee('Reset attempt');
        // The poll skips its swap on this hook; without it an open menu would be
        // destroyed within 4 seconds.
        $response->assertSee('data-menu-open', false);
    }

    public function test_teacher_can_open_a_candidate_attempt_detail(): void
    {
        [, , $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;

        $this->actingAs($session->teacher)
            ->get(route('teacher.exam-sessions.attempts.show', [$session, $attempt]))
            ->assertOk()
            ->assertSee('Guest')
            ->assertSee('Question map');
    }

    public function test_question_map_shows_unanswered_questions_before_any_answer_is_saved(): void
    {
        // The whole point of building the grid from the module's questions
        // rather than from saved answers: mid-module a blank has no
        // user_test_answers row, and blanks are what the teacher is watching for.
        [, , $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;

        $this->assertSame(0, $attempt->userAnswers()->count());

        $this->actingAs($session->teacher)
            ->get(route('teacher.exam-sessions.attempts.show', [$session, $attempt]))
            ->assertOk()
            ->assertSee('qmap-cell--blank', false)
            ->assertSee('0 / 1');
    }

    public function test_question_map_withholds_correctness_until_the_module_is_submitted(): void
    {
        // A teacher standing over a shoulder must not be able to read the answer
        // key off a live screen, so correct/incorrect only appears post-submit.
        [, $modules, $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;

        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $modules[0]->id,
            'question_id' => $modules[0]->questions()->first()->id,
            'selected_answer' => 'A',
            'is_correct' => true,
        ]);

        $this->actingAs($session->teacher)
            ->get(route('teacher.exam-sessions.attempts.show', [$session, $attempt]))
            ->assertOk()
            ->assertSee('qmap-cell--answered', false)
            ->assertDontSee('qmap-cell--correct', false);
    }

    public function test_loading_skeleton_is_removed_from_the_dom_rather_than_hidden(): void
    {
        // Tailwind runs in `important` mode (app.css:1), so `.flex` lands as
        // `display:flex!important` and x-show's inline `display:none` can never
        // hide it — the skeleton stayed on screen next to the loaded question.
        // x-if removes the node instead, which no CSS can override.
        [, , $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;

        $response = $this->actingAs($session->teacher)
            ->get(route('teacher.exam-sessions.attempts.show', [$session, $attempt]))
            ->assertOk();

        $response->assertSee('<template x-if="loading">', false);
        $response->assertDontSee('x-show="loading"', false);
    }

    public function test_attempt_monitor_polls_and_re_authorizes_on_every_render(): void
    {
        [, $modules, $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;

        $component = Livewire::actingAs($session->teacher)
            ->test(ExamAttemptMonitor::class, ['examSession' => $session, 'attempt' => $attempt])
            ->assertSee('Question map')
            ->assertSee('wire:poll.5s', false);

        // A poll must reflect work saved since first paint — that is the whole
        // point of moving this off a static page render.
        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $modules[0]->id,
            'question_id' => $modules[0]->questions()->first()->id,
            'selected_answer' => 'A',
            'is_correct' => true,
        ]);

        $component->call('$refresh')->assertSee('qmap-cell--answered', false);
    }

    public function test_attempt_monitor_stops_polling_once_the_attempt_is_finished(): void
    {
        [, , $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;
        $attempt->update(['status' => 'completed', 'completed_at' => now(), 'total_score' => 1200]);

        Livewire::actingAs($session->teacher)
            ->test(ExamAttemptMonitor::class, ['examSession' => $session, 'attempt' => $attempt])
            ->assertDontSee('wire:poll', false);
    }

    public function test_attempt_monitor_refuses_a_teacher_who_does_not_own_the_session(): void
    {
        // The Livewire update endpoint is directly callable with a client-supplied
        // payload, so authorising only in mount() would leave polls unguarded.
        [, , $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;

        Livewire::actingAs($this->approvedTeacher())
            ->test(ExamAttemptMonitor::class, ['examSession' => $session, 'attempt' => $attempt])
            ->assertForbidden();
    }

    public function test_question_detail_reports_how_the_answer_was_graded(): void
    {
        [, $modules, $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;
        $attempt->update(['status' => 'completed', 'completed_at' => now()]);

        $answer = UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $modules[0]->id,
            'question_id' => $modules[0]->questions()->first()->id,
            'selected_answer' => 'A',
            'is_correct' => true,
        ]);

        $this->actingAs($session->teacher)
            ->get(route('teacher.exam-sessions.attempts.question-preview', [$session, $answer]))
            ->assertOk()
            ->assertSee('Correct')
            ->assertSee('Response: A')
            ->assertSee('Information And Ideas')
            ->assertSee('Module 1');
    }

    public function test_question_detail_always_reports_time_spent_even_when_zero(): void
    {
        // The old badge rendered nothing when both spent and expected were 0 —
        // the common case for a question the candidate never opened — so the
        // modal looked like it had captured no timing at all.
        [, $modules, $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;

        $answer = UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $modules[0]->id,
            'question_id' => $modules[0]->questions()->first()->id,
            'selected_answer' => null,
            'is_correct' => false,
            'time_spent' => 0,
        ]);

        $this->actingAs($session->teacher)
            ->get(route('teacher.exam-sessions.attempts.question-preview', [$session, $answer]))
            ->assertOk()
            ->assertSee('Time: 0s');
    }

    public function test_question_detail_flags_a_question_that_ran_over_pace(): void
    {
        [, $modules, $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;

        $question = $modules[0]->questions()->first();
        $question->update(['expected_time' => 60]);

        $answer = UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $modules[0]->id,
            'question_id' => $question->id,
            'selected_answer' => 'A',
            'is_correct' => true,
            'time_spent' => 150,
        ]);

        $this->actingAs($session->teacher)
            ->get(route('teacher.exam-sessions.attempts.question-preview', [$session, $answer]))
            ->assertOk()
            // Formatted, not raw seconds, once past a minute.
            ->assertSee('2m 30s')
            ->assertSee('1m expected')
            ->assertSee('Over pace');
    }

    public function test_attempt_detail_is_scoped_to_its_own_session(): void
    {
        [, , $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $otherTeacher = $this->approvedTeacher();
        $otherSession = ExamSession::create([
            'teacher_id' => $otherTeacher->id,
            'test_id' => $this->privateTeacherTest($otherTeacher)->id,
            'title' => 'Unrelated Session',
        ]);

        // Right teacher, wrong session: the attempt does not belong to it.
        $this->actingAs($otherTeacher)
            ->get(route('teacher.exam-sessions.attempts.show', [$otherSession, $attempt]))
            ->assertNotFound();

        // Wrong teacher entirely.
        $this->actingAs($otherTeacher)
            ->get(route('teacher.exam-sessions.attempts.show', [$attempt->examSession, $attempt]))
            ->assertForbidden();
    }

    public function test_question_preview_rejects_an_answer_from_another_session(): void
    {
        [, $modules, $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;

        $answer = UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $modules[0]->id,
            'question_id' => $modules[0]->questions()->first()->id,
            'selected_answer' => 'A',
            'is_correct' => true,
        ]);

        $this->actingAs($session->teacher)
            ->get(route('teacher.exam-sessions.attempts.question-preview', [$session, $answer]))
            ->assertOk();

        $otherTeacher = $this->approvedTeacher();
        $otherSession = ExamSession::create([
            'teacher_id' => $otherTeacher->id,
            'test_id' => $this->privateTeacherTest($otherTeacher)->id,
            'title' => 'Unrelated Session',
        ]);

        $this->actingAs($otherTeacher)
            ->get(route('teacher.exam-sessions.attempts.question-preview', [$otherSession, $answer]))
            ->assertForbidden();
    }

    public function test_hosting_teacher_can_open_the_score_report_for_a_session_attempt(): void
    {
        [, , $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;
        $attempt->update(['status' => 'completed', 'completed_at' => now(), 'total_score' => 1200]);

        $this->actingAs($session->teacher)
            ->get(route('student.scores.show', $attempt))
            ->assertOk();

        // The widened policy must stay scoped to the hosting teacher.
        $this->actingAs($this->approvedTeacher())
            ->get(route('student.scores.show', $attempt))
            ->assertForbidden();
    }

    public function test_reset_attempt_allows_a_retake(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $session = ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Mock Exam',
        ]);
        $guest = $this->makeGuest();
        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'exam_session_id' => $session->id,
            'guest_name' => 'Guest',
            'status' => 'in_progress',
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.exam-sessions.attempts.reset', [$session, $attempt]))
            ->assertRedirect();

        $this->assertSame('abandoned', $attempt->fresh()->status);
    }

    public function test_paused_session_blocks_autosave_in_engine(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $session = ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Mock Exam',
            'status' => 'paused',
        ]);

        $guest = User::factory()->create(['role' => 'student', 'is_guest' => true, 'email_verified_at' => now()]);
        $module = $test->sections()->first()->modules()->first();
        $question = $module->questions()->first();

        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'exam_session_id' => $session->id,
            'status' => 'in_progress',
            'current_module_id' => $module->id,
        ]);

        $this->actingAs($guest)
            ->postJson(route('engine.test.autosave-module'), [
                'user_test_id' => $attempt->id,
                'module_id' => $module->id,
                'answers' => [$question->id => 'A'],
            ])
            ->assertStatus(409);
    }

    public function test_teacher_can_fetch_live_status_json(): void
    {
        $teacher = $this->approvedTeacher();
        $test = $this->privateTeacherTest($teacher);
        $session = ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Live Mock Session',
            'status' => 'active',
        ]);

        $this->actingAs($teacher)
            ->getJson(route('teacher.exam-sessions.live-status', $session))
            ->assertOk()
            ->assertJsonStructure([
                'session_status',
                'candidate_count',
                'in_progress_count',
                'completed_count',
                'average_score',
                'html',
            ]);
    }

    public function test_live_status_html_matches_the_roster_rendered_on_the_page(): void
    {
        // The whole point of shipping rendered HTML instead of JSON is that the
        // poll cannot drift from first paint — assert both actually agree.
        [, , $attempt] = $this->examSessionAttempt(moduleCount: 1);
        $session = $attempt->examSession;
        $teacher = $session->teacher;

        $this->actingAs($teacher)
            ->get(route('teacher.exam-sessions.show', $session))
            ->assertOk()
            ->assertSee('Guest')
            ->assertSee('exam-candidate', false);

        $this->actingAs($teacher)
            ->getJson(route('teacher.exam-sessions.live-status', $session))
            ->assertOk()
            ->assertJsonFragment(['candidate_count' => 1])
            ->assertSee('exam-candidate', false);
    }

    /**
     * `User::create()` respects `$fillable`, which deliberately excludes
     * `role` and `email_verified_at` (see User.php — both are set via direct
     * property assignment elsewhere, e.g. ExamSessionService::provisionGuest,
     * RegisterController). Passing them inside a plain `create([...])` array
     * silently drops them rather than erroring, which only surfaces once a
     * test actually exercises the `verified` middleware — mirror the
     * production two-step pattern here instead of `User::factory()`, which
     * bypasses fillable guarding entirely and would mask the same mistake in
     * application code.
     */
    private function makeGuest(array $overrides = []): User
    {
        $guest = User::create(array_merge([
            'name' => 'Guest',
            'username' => 'guest_'.Str::lower(Str::random(16)),
            'email' => 'guest_'.Str::lower(Str::random(20)).'@guest.examsession.local',
            'password' => bcrypt('secret'),
            'is_guest' => true,
        ], $overrides));
        $guest->role = 'student';
        $guest->email_verified_at = now();
        $guest->save();

        return $guest;
    }

    private function approvedTeacher(): User
    {
        return User::factory()->create([
            'role' => 'teacher',
            'teacher_approval_status' => 'approved',
            'email_verified_at' => now(),
        ]);
    }

    private function privateTeacherTest(User $teacher): Test
    {
        $test = Test::create([
            'title' => 'Private Mock Exam Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
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
            'is_public' => false,
        ]);

        $module = Module::create([
            'section_id' => $section->id,
            'key' => 'EXAM_SESSION_TEST_'.$test->id.'_RW_M1',
            'module_number' => 1,
            'difficulty_level' => Module::DIFFICULTY_STANDARD,
            'duration_minutes' => 32,
            'total_questions' => 1,
            'order' => 1,
            'created_by' => $teacher->id,
            'is_public' => false,
        ]);

        $module->sections()->syncWithoutDetaching([$section->id]);

        $passage = Passage::create([
            'content' => 'A short reading passage.',
            'passage_type' => 'single',
            'genre' => 'humanities',
        ]);

        $question = Question::create([
            'passage_id' => $passage->id,
            'stem' => 'Which choice best supports the claim?',
            'question_type' => Question::TYPE_MCQ,
            'difficulty' => 'easy',
            'is_pretest' => false,
            'is_complete' => true,
            'section_type' => Section::TYPE_RW,
            'skill_domain' => 'information_and_ideas',
            'created_by' => $teacher->id,
        ]);

        foreach (['A', 'B', 'C', 'D'] as $index => $label) {
            AnswerChoice::create([
                'question_id' => $question->id,
                'label' => $label,
                'content' => "Choice {$label}",
                'is_correct' => $index === 0,
                'order' => $index + 1,
            ]);
        }

        $module->questions()->attach($question->id, ['position' => 1]);

        return $test;
    }
}
