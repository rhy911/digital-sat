<?php

namespace Tests\Feature;

use App\Http\Controllers\Engine\AttemptController;
use App\Models\AnswerChoice;
use App\Models\ExamSession;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test as SatTest;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Notifications\VerifyEmailNotification;
use App\Services\ScoreReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicTrialTest extends TestCase
{
    use RefreshDatabase;

    public function test_interface_demo_is_public_and_does_not_create_a_guest_or_attempt(): void
    {
        $this->withoutVite();
        $this->get(route('public-trial.demo', 'reading_writing'))
            ->assertOk()
            ->assertSee('Interface demo')
            ->assertSee('isPublicTrialDemo')
            ->assertSee('previewNextUrl');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_tests', 0);
    }

    public function test_guest_trial_session_cookie_expires_with_the_browser_session(): void
    {
        $guest = User::factory()->create(['is_guest' => true, 'role' => 'student', 'email_verified_at' => now()]);

        $cookie = $this->actingAs($guest)->withSession(['public_trial_session' => true])->get(route('public-trial.index'))
            ->getCookie(config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertSame(0, $cookie->getExpiresTime());
    }

    public function test_teacher_exam_guest_keeps_the_existing_persistent_cookie_behavior(): void
    {
        $guest = User::factory()->create(['is_guest' => true, 'role' => 'student', 'email_verified_at' => now()]);

        $cookie = $this->actingAs($guest)->get(route('landing'))->getCookie(config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertGreaterThan(time(), $cookie->getExpiresTime());
    }

    public function test_configured_module_start_creates_a_marked_guest_attempt(): void
    {
        $this->withoutVite();
        [$test] = $this->moduleTest('Public trial module');
        config(['public_trial.tests.reading_writing' => $test->ulid]);

        $response = $this->post(route('public-trial.start'), [
            'experience' => 'module',
            'section' => 'reading_writing',
        ]);

        $attempt = UserTest::query()->sole();
        $this->assertTrue($attempt->is_public_trial);
        $this->assertNotNull($attempt->guest_trial_expires_at);
        $this->assertEquals($attempt->created_at->copy()->addHours(24)->timestamp, $attempt->guest_trial_absolute_expires_at->timestamp);
        $this->assertTrue($attempt->user->is_guest);
        $this->assertSame('section', $attempt->attempt_type);
        $this->assertSame('reading_writing', $attempt->section_type);
        $response->assertRedirect(route('engine.session', ['ulid' => $attempt->currentModule->ulid, 'attempt' => $attempt->ulid]));
    }

    public function test_expired_guest_trial_returns_410_and_cannot_be_resumed(): void
    {
        [$test, $module] = $this->moduleTest('Expired public trial');
        $guest = User::factory()->create(['is_guest' => true, 'role' => 'student', 'email_verified_at' => now()]);
        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'current_module_id' => $module->id,
            'status' => 'in_progress',
            'is_public_trial' => true,
            'guest_trial_expires_at' => now()->subSecond(),
            'guest_trial_absolute_expires_at' => now()->addHours(1),
        ]);

        $this->actingAs($guest)->get(route('public-trial.result', $attempt))->assertRedirect(route('public-trial.expired', ['attempt' => $attempt->id]));
        $this->getJson(route('public-trial.result', $attempt))->assertStatus(410)->assertJsonPath('error', 'trial_expired');
        $this->get(route('engine.session', ['ulid' => $module->ulid, 'attempt' => $attempt->ulid]))
            ->assertRedirect(route('public-trial.expired', ['attempt' => $attempt->id]));

        $this->assertSame('in_progress', $attempt->fresh()->status);
        $this->assertTrue($attempt->fresh()->guest_trial_expires_at->isPast());
    }

    public function test_creation_budget_rejects_new_work_but_allows_resume_without_abandoning_current_attempt(): void
    {
        [$test] = $this->moduleTest('Rate limited public trial');
        config(['public_trial.tests.reading_writing' => $test->ulid]);
        $guest = User::factory()->create(['is_guest' => true, 'role' => 'student', 'email_verified_at' => now()]);
        $this->actingAs($guest);

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('public-trial.start'), [
                'experience' => 'module', 'section' => 'reading_writing',
            ])->assertRedirect();
            if ($i < 9) {
                UserTest::where('user_id', $guest->id)->where('status', 'in_progress')->update(['status' => 'completed']);
            }
        }
        $active = UserTest::where('user_id', $guest->id)->where('status', 'in_progress')->sole();

        // Active attempt cannot be abandoned/restarted even if requested with fresh
        $this->post(route('public-trial.start'), [
            'experience' => 'module', 'section' => 'reading_writing', 'mode' => 'fresh', 'confirm_fresh' => true,
        ])->assertRedirect();
        $this->assertSame('in_progress', $active->fresh()->status);
        $this->assertSame(10, UserTest::where('user_id', $guest->id)->count());

        $this->post(route('public-trial.start'), [
            'experience' => 'module', 'section' => 'reading_writing', 'mode' => 'continue',
        ])->assertRedirect();
        $this->assertSame(10, UserTest::where('user_id', $guest->id)->count());

        Auth::logout();
        $this->post(route('public-trial.start'), [
            'experience' => 'module', 'section' => 'reading_writing', 'mode' => 'fresh', 'confirm_fresh' => true,
        ])->assertStatus(429);
        $this->assertSame(1, User::where('is_guest', true)->count());
    }

    public function test_existing_uncleaned_trial_forces_continue_and_blocks_new_attempt_creation(): void
    {
        [$test, $module] = $this->moduleTest('Restartable public trial');
        config(['public_trial.tests.reading_writing' => $test->ulid]);
        $guest = User::factory()->create(['is_guest' => true, 'role' => 'student', 'email_verified_at' => now()]);
        $active = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
            'current_module_id' => $module->id,
            'is_public_trial' => true,
        ]);

        $sessionUrl = route('engine.session', ['ulid' => $module->ulid, 'attempt' => $active->ulid]);

        // Attempting fresh start silently routes to the existing active attempt
        $this->actingAs($guest)->post(route('public-trial.start'), [
            'experience' => 'module', 'section' => 'reading_writing', 'mode' => 'fresh',
        ])->assertRedirect($sessionUrl);
        $this->assertSame('in_progress', $active->fresh()->status);
        $this->assertSame(1, UserTest::where('user_id', $guest->id)->count());

        // Even with confirm_fresh, active attempt is preserved and forced to continue
        $this->post(route('public-trial.start'), [
            'experience' => 'module', 'section' => 'reading_writing', 'mode' => 'fresh', 'confirm_fresh' => true,
        ])->assertRedirect($sessionUrl);
        $this->assertSame('in_progress', $active->fresh()->status);
        $this->assertSame(1, UserTest::where('user_id', $guest->id)->where('status', 'in_progress')->count());
        $this->assertSame(1, UserTest::where('user_id', $guest->id)->count());
    }

    public function test_guest_trial_report_is_partial_and_full_report_redirects_to_it(): void
    {
        $this->withoutVite();
        [$test] = $this->moduleTest('Partial public trial');
        $guest = User::factory()->create(['is_guest' => true, 'role' => 'student', 'email_verified_at' => now()]);
        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
            'is_public_trial' => true,
            'attempt_type' => 'section',
            'section_type' => 'reading_writing',
        ]);

        $this->actingAs($guest)->get(route('public-trial.result', $attempt))
            ->assertOk()
            ->assertSee('Practice result')
            ->assertSee('Create a free account')
            ->assertDontSee('questionDataMap');

        $this->get(route('student.scores.show', $attempt))->assertRedirect(route('public-trial.result', $attempt));
        $this->get(route('student.scores.export-pdf', $attempt))->assertForbidden();
    }

    public function test_signup_merges_trial_and_verification_link_returns_to_its_report(): void
    {
        $this->withoutVite();
        Notification::fake();
        [$test, $module, $question] = $this->moduleTest('Mergeable public trial');
        $guest = User::factory()->create(['is_guest' => true, 'role' => 'student', 'email_verified_at' => now()]);
        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
            'is_public_trial' => true,
        ]);
        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $question->id,
            'selected_answer' => 'A',
            'is_correct' => true,
            'question_snapshot' => ['stem' => 'Sample question', 'choices' => [], 'spr_answers' => [], 'explanation' => ['explanation' => 'A sample explanation.']],
        ]);

        $this->actingAs($guest)->post(route('public-trial.account', [$attempt, 'signup']))
            ->assertRedirect(route('signup', ['role' => 'student']));
        $this->assertGuest();

        $this->post(route('signup'), [
            'username' => 'trialstudent',
            'email' => 'trialstudent@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'student',
        ])->assertRedirect(route('verify.email.notice'));

        $realUser = User::where('email', 'trialstudent@example.test')->firstOrFail();
        $this->assertSame($realUser->id, $attempt->fresh()->user_id);
        $this->assertTrue($attempt->fresh()->is_public_trial);
        Notification::assertSentTo($realUser, VerifyEmailNotification::class);
        $notification = Notification::sent($realUser, VerifyEmailNotification::class)->first();
        $verifyUrl = $notification->toMail($realUser)->actionUrl;

        $this->get($verifyUrl)->assertOk()->assertSee(route('student.scores.show', $attempt), false);
        $this->assertTrue($realUser->fresh()->hasVerifiedEmail());
    }

    public function test_invalid_and_incompatible_configurations_are_rejected(): void
    {
        $this->withoutVite();

        // 1. Inactive test
        $inactive = SatTest::create([
            'title' => 'Inactive Test',
            'test_type' => SatTest::TYPE_MODULE_ONLY,
            'status' => 'draft',
            'is_public' => false,
        ]);
        config(['public_trial.tests.reading_writing' => $inactive->ulid]);
        $this->post(route('public-trial.start'), [
            'experience' => 'module',
            'section' => 'reading_writing',
        ])->assertSessionHas('error');

        // 2. Incompatible count
        $incompatible = SatTest::create([
            'title' => 'Incompatible Count Test',
            'test_type' => SatTest::TYPE_MODULE_ONLY,
            'status' => 'active',
            'is_public' => true,
        ]);
        $sec = Section::create(['test_id' => $incompatible->id, 'name' => 'Reading and Writing', 'type' => Section::TYPE_RW, 'order' => 1]);
        Module::create([
            'section_id' => $sec->id,
            'module_number' => 1,
            'difficulty_level' => Module::DIFFICULTY_STANDARD,
            'duration_minutes' => Module::RW_DURATION,
            'total_questions' => 5,
            'order' => 1,
            'is_public' => true,
        ]);
        config(['public_trial.tests.reading_writing' => $incompatible->ulid]);
        $this->post(route('public-trial.start'), [
            'experience' => 'module',
            'section' => 'reading_writing',
        ])->assertSessionHas('error');

        // 3. Guest calling startTest API route on invalid test receives 403
        $guest = User::factory()->create(['is_guest' => true, 'role' => 'student', 'email_verified_at' => now()]);
        $this->actingAs($guest)->postJson(route('engine.test.start', $incompatible->id))->assertForbidden();
    }

    public function test_replayed_first_start_requests_reuse_provisioned_guest_and_attempt(): void
    {
        $this->withoutVite();
        [$test] = $this->moduleTest('Concurrency public trial');
        config(['public_trial.tests.reading_writing' => $test->ulid]);

        $this->post(route('public-trial.start'), [
            'experience' => 'module',
            'section' => 'reading_writing',
        ])->assertRedirect();

        $guest = User::where('is_guest', true)->sole();
        $attempt = UserTest::where('user_id', $guest->id)->sole();

        $sessionId = session()->getId();
        Cache::put("public_trial_guest:{$sessionId}", $guest->id, 120);

        $this->flushSession();
        session()->setId($sessionId);

        $this->withSession(['_token' => 'dummy'])->post(route('public-trial.start'), [
            'experience' => 'module',
            'section' => 'reading_writing',
        ])->assertRedirect();

        $this->assertSame(1, User::where('is_guest', true)->count());
        $this->assertSame(1, UserTest::count());
        $this->assertSame($attempt->id, UserTest::sole()->id);
    }

    public function test_resuming_existing_attempt_works_even_if_publication_status_changes(): void
    {
        [$test, $module] = $this->moduleTest('Status change trial');
        $guest = User::factory()->create(['is_guest' => true, 'role' => 'student', 'email_verified_at' => now()]);
        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
            'current_module_id' => $module->id,
            'is_public_trial' => true,
        ]);

        $test->update(['status' => 'draft', 'is_public' => false]);

        $controller = app(AttemptController::class);
        $resumed = $controller->startPracticeAttempt($guest, $test, 'continue', true);

        $this->assertSame($attempt->id, $resumed->id);
        $this->assertSame('in_progress', $resumed->status);
    }

    public function test_partial_report_snapshot_only_sample_rendering_and_spr_zero(): void
    {
        $this->withoutVite();
        [$test, $module] = $this->moduleTest('Report snapshot trial');
        $guest = User::factory()->create(['is_guest' => true, 'role' => 'student', 'email_verified_at' => now()]);
        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
            'is_public_trial' => true,
            'attempt_type' => 'section',
            'section_type' => 'reading_writing',
        ]);

        // Answer 1: Incomplete snapshot (missing choices) -> should be skipped by hasSufficientSnapshot
        $q1 = Question::create([
            'stem' => 'Incomplete snapshot stem',
            'question_type' => Question::TYPE_MCQ,
            'difficulty' => 'easy',
            'section_type' => Section::TYPE_RW,
            'skill_domain' => 'craft_and_structure',
            'is_complete' => true,
        ]);
        $ans1 = UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $q1->id,
            'selected_answer' => null,
            'is_correct' => false,
            'question_snapshot' => ['stem' => 'Incomplete stem without choices or explanation'],
        ]);

        // Answer 2: SPR with answer "0" -> treated as answered, not omitted!
        $q2 = Question::create([
            'stem' => 'Calculate $x - x$',
            'question_type' => Question::TYPE_SPR,
            'difficulty' => 'easy',
            'section_type' => Section::TYPE_MATH,
            'skill_domain' => 'algebra',
            'is_complete' => true,
        ]);
        $ans2 = UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $q2->id,
            'selected_answer' => '0',
            'is_correct' => true,
            'question_snapshot' => [
                'stem' => 'Calculate $x - x$',
                'question_type' => 'student_produced_response',
                'spr_answers' => [['answer' => '0']],
                'explanation' => ['explanation' => 'Any number minus itself is 0.'],
            ],
        ]);

        // Answer 3: MCQ missed answer with sufficient snapshot including passage
        $q3 = Question::create([
            'stem' => 'What is the main idea?',
            'question_type' => Question::TYPE_MCQ,
            'difficulty' => 'medium',
            'section_type' => Section::TYPE_RW,
            'skill_domain' => 'craft_and_structure',
            'is_complete' => true,
        ]);
        $ans3 = UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $q3->id,
            'selected_answer' => 'B',
            'is_correct' => false,
            'question_snapshot' => [
                'stem' => 'What is the main idea?',
                'question_type' => 'multiple_choice',
                'section_type' => 'reading_writing',
                'passage' => ['content' => 'The passage explores urban rail transit trends.'],
                'choices' => [
                    ['label' => 'A', 'content' => 'Correct idea', 'is_correct' => true, 'order' => 1],
                    ['label' => 'B', 'content' => 'Incorrect idea', 'is_correct' => false, 'order' => 2],
                ],
                'explanation' => ['explanation' => 'Choice A directly states the thesis.'],
            ],
        ]);

        $reports = app(ScoreReportService::class);

        // Verify incomplete snapshot rejection and SPR numeric "0" handling
        $this->assertFalse($reports->hasSufficientSnapshot($ans1));
        $this->assertTrue($reports->hasSufficientSnapshot($ans2));
        $this->assertTrue($reports->hasSufficientSnapshot($ans3));
        $this->assertSame('0', $reports->formatSingleAnswer($ans2)['correct_answer']);

        // Mutate live question to guarantee sample review reads snapshot only
        $q3->update(['stem' => 'Mutated live question stem that should NOT appear']);

        $summary = $reports->buildPublicTrialSummary($attempt);

        $this->assertSame(1, $summary['stats']['total']['correct']);
        $this->assertSame(1, $summary['stats']['total']['incorrect']);
        $this->assertSame(1, $summary['stats']['total']['omitted']);

        $this->assertNotNull($summary['sampleReview']);
        $this->assertSame('B', $summary['sampleReview']['selected_answer']);
        $this->assertSame('A', $summary['sampleReview']['correct_answer']);
        $this->assertStringContainsString('What is the main idea?', $summary['sampleReview']['stem']);
        $this->assertStringNotContainsString('Mutated live question stem', $summary['sampleReview']['stem']);
        $this->assertStringContainsString('The passage explores urban rail transit trends.', $summary['sampleReview']['passage']);

        $this->actingAs($guest)->get(route('public-trial.result', $attempt))
            ->assertOk()
            ->assertSee('Practice result')
            ->assertSee('What is the main idea?')
            ->assertDontSee('questionDataMap')
            ->assertDontSee('data-bookmark')
            ->assertDontSee('data-crossout');

        $this->getJson(route('student.scores.show', $attempt))->assertForbidden();
    }

    public function test_mixed_teacher_exam_and_public_trial_guest_history(): void
    {
        [$test] = $this->moduleTest('Mixed history test');
        $guest = User::factory()->create(['is_guest' => true, 'role' => 'student', 'email_verified_at' => now()]);

        $publicTrial = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now()->subDay(),
            'is_public_trial' => true,
        ]);

        $teacher = User::factory()->create(['role' => 'teacher', 'teacher_approval_status' => 'approved']);
        $examSession = ExamSession::create([
            'test_id' => $test->id,
            'teacher_id' => $teacher->id,
            'title' => 'Classroom Exam',
        ]);
        $examAttempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'exam_session_id' => $examSession->id,
            'status' => 'completed',
            'completed_at' => now(),
            'is_public_trial' => false,
        ]);

        $visibleAttempts = UserTest::where('user_id', $guest->id)
            ->visibleInGuestAnalytics($guest)
            ->get();

        $this->assertTrue($visibleAttempts->contains('id', $examAttempt->id));
        $this->assertFalse($visibleAttempts->contains('id', $publicTrial->id));
    }

    public function test_verification_link_stale_fallback_and_teacher_approval_flow(): void
    {
        $this->withoutVite();
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => null]);

        $staleTrialUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $student->id, 'hash' => sha1($student->getEmailForVerification()), 'trial' => 'nonexistentulid']
        );
        $this->get($staleTrialUrl)->assertRedirect(route('home'))->assertSessionHas('warning');

        $student2 = User::factory()->create(['role' => 'student', 'email_verified_at' => null]);
        $ordinaryUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $student2->id, 'hash' => sha1($student2->getEmailForVerification())]
        );
        $this->get($ordinaryUrl)->assertOk()->assertSee(route('dashboard'), false);
        Auth::logout();

        $this->post(route('signup'), [
            'username' => 'newteacher',
            'email' => 'teacher@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'teacher',
        ])->assertRedirect(route('verify.email.notice'))->assertSessionHas('warning');

        $teacher = User::where('email', 'teacher@example.test')->sole();
        $this->assertSame('pending', $teacher->teacher_approval_status);
        Auth::logout();

        // Pending teacher signin routes to application status
        $pendingTeacher = User::factory()->create([
            'role' => 'teacher',
            'teacher_approval_status' => 'pending',
            'email_verified_at' => now(),
            'password' => bcrypt('password123'),
        ]);
        $this->post(route('signin'), [
            'email' => $pendingTeacher->email,
            'password' => 'password123',
        ])->assertRedirect(route('teacher.application.status'));
        Auth::logout();

        // Approved teacher signin routes to home
        $approvedTeacher = User::factory()->create([
            'role' => 'teacher',
            'teacher_approval_status' => 'approved',
            'email_verified_at' => now(),
            'password' => bcrypt('password123'),
        ]);
        $this->post(route('signin'), [
            'email' => $approvedTeacher->email,
            'password' => 'password123',
        ])->assertRedirect(route('home'));
    }

    public function test_demo_flow_complete_navigation(): void
    {
        $this->withoutVite();

        $rwResponse = $this->get(route('public-trial.demo', 'reading_writing'));
        $rwResponse->assertOk()
            ->assertSee('Reading and Writing')
            ->assertSee('isPublicTrialDemo')
            ->assertSee('previewNextUrl')
            ->assertSee('Exit to DigiSAT')
            ->assertSee('id="exitTestBtn"', false)
            ->assertSee('data-engine-exit', false)
            ->assertSee('confirmExitTest', false);

        $mathResponse = $this->get(route('public-trial.demo', 'math'));
        $mathResponse->assertOk()
            ->assertSee('Math')
            ->assertSee('isPublicTrialDemo')
            ->assertSee('previewExitUrl')
            ->assertSee('Exit to DigiSAT')
            ->assertSee('id="exitTestBtn"', false)
            ->assertSee('data-engine-exit', false)
            ->assertSee('confirmExitTest', false);
    }

    public function test_guest_cannot_access_authenticated_home_or_dashboard(): void
    {
        $guest = User::factory()->create([
            'name' => 'Guest',
            'is_guest' => true,
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($guest);

        $this->get(route('home'))->assertRedirect(route('public-trial.index'));
        $this->get('/dashboard')->assertRedirect(route('public-trial.index'));

        // If guest has completed a public trial, they are redirected to their trial result
        [$test] = $this->moduleTest('DigiSAT Trial — Reading and Writing', Section::TYPE_RW);
        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'is_public_trial' => true,
            'completed_at' => now(),
        ]);

        $this->get(route('home'))->assertRedirect(route('public-trial.result', $attempt));
    }

    public function test_guest_visiting_landing_sees_public_landing(): void
    {
        $guest = User::factory()->create([
            'name' => 'Guest',
            'is_guest' => true,
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($guest)
            ->get(route('landing'))
            ->assertOk()
            ->assertSee('DigiSAT');
    }

    public function test_guest_visiting_auth_routes_is_logged_out_with_session_stashed(): void
    {
        $guest = User::factory()->create([
            'name' => 'Guest',
            'is_guest' => true,
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        [$test] = $this->moduleTest('DigiSAT Trial — Math', Section::TYPE_MATH);
        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'is_public_trial' => true,
            'completed_at' => now(),
        ]);

        $this->actingAs($guest)
            ->get(route('signin'))
            ->assertOk()
            ->assertSessionHas('link_guest_user_id', $guest->id)
            ->assertSessionHas('link_trial_attempt_ulid', $attempt->ulid);

        $this->assertGuest();
    }

    public function test_terminal_single_module_trial_scores_inline_without_queue(): void
    {
        [$test, $module] = $this->moduleTest('DigiSAT Trial — Reading and Writing', Section::TYPE_RW);
        config(['scoring.inline_non_final' => true]);

        $guest = User::factory()->create([
            'name' => 'Guest',
            'is_guest' => true,
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'current_module_id' => $module->id,
            'status' => 'in_progress',
            'is_public_trial' => true,
            'started_at' => now(),
        ]);

        $this->actingAs($guest)->withAttemptController($attempt)
            ->postJson(route('engine.test.submit-module'), [
                'user_test_id' => $attempt->id,
                'module_id' => $module->id,
                'answers' => [],
                'question_times' => [],
                'timed_out' => false,
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'test_completed' => true,
                'redirect_url' => route('public-trial.result', $attempt),
            ]);

        $this->assertEquals('completed', $attempt->fresh()->status);
    }

    public function test_public_trial_and_exam_join_prompt_confirmation_modal_before_entering_engine(): void
    {
        $response = $this->get(route('public-trial.index'));
        $response->assertOk()
            ->assertSee('guest-engine-confirm-modal')
            ->assertSee('trialLauncher')
            ->assertSee('Before entering the test engine')
            ->assertSee('Cancel');

        // Exam join also prompts confirmation modal
        [$test] = $this->moduleTest('DigiSAT Trial — Reading and Writing', Section::TYPE_RW);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $session = ExamSession::create([
            'test_id' => $test->id,
            'teacher_id' => $teacher->id,
            'title' => 'Sample Live Exam',
            'code' => 'ABC999',
            'status' => 'active',
            'duration_minutes' => 60,
        ]);

        $joinResponse = $this->get(route('exam-join.show', $session->code));
        $joinResponse->assertOk()
            ->assertSee('exam-join-confirm-modal')
            ->assertSee('Ready to begin your exam?');
    }

    public function test_public_trial_displays_continue_options_when_in_progress_attempt_exists(): void
    {
        [$test, $module] = $this->moduleTest('DigiSAT Trial — Reading and Writing', Section::TYPE_RW);
        config(['public_trial.tests.reading_writing' => $test->ulid]);
        $guest = User::factory()->create(['is_guest' => true, 'role' => 'student', 'email_verified_at' => now()]);

        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'current_module_id' => $module->id,
            'status' => 'in_progress',
            'is_public_trial' => true,
            'guest_trial_expires_at' => now()->addMinutes(30),
            'guest_trial_absolute_expires_at' => now()->addHours(24),
        ]);

        $sessionUrl = route('engine.session', ['ulid' => $module->ulid, 'attempt' => $attempt->ulid]);

        $response = $this->actingAs($guest)->get(route('public-trial.index'));
        $response->assertOk()
            ->assertSee('You have an in-progress practice attempt')
            ->assertSee('Continue Reading &amp; Writing', false)
            ->assertDontSee('DigiSAT Trial — Reading and Writing')
            ->assertDontSee('Start over and replace the in-progress attempt')
            ->assertSee($sessionUrl, false);

        // Submitting with default/continue mode resumes the in-progress attempt
        $startResponse = $this->actingAs($guest)->post(route('public-trial.start'), [
            'experience' => 'module',
            'section' => 'reading_writing',
            'mode' => 'continue',
        ]);
        $startResponse->assertRedirect($sessionUrl);
        $this->assertSame(1, UserTest::where('user_id', $guest->id)->count());
        $this->assertSame('in_progress', $attempt->fresh()->status);
    }

    /** @return array{0: SatTest, 1: Module, 2: Question} */
    private function moduleTest(string $title, string $sectionType = Section::TYPE_RW): array
    {
        $test = SatTest::create([
            'title' => $title,
            'test_type' => SatTest::TYPE_MODULE_ONLY,
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);
        $section = Section::create([
            'test_id' => $test->id,
            'name' => $sectionType === Section::TYPE_RW ? 'Reading and Writing' : 'Math',
            'type' => $sectionType,
            'order' => 1,
        ]);
        $expectedCount = $sectionType === Section::TYPE_RW ? Module::RW_QUESTIONS : Module::MATH_QUESTIONS;
        $expectedDuration = $sectionType === Section::TYPE_RW ? Module::RW_DURATION : Module::MATH_DURATION;
        $module = Module::create([
            'section_id' => $section->id,
            'module_number' => 1,
            'difficulty_level' => Module::DIFFICULTY_STANDARD,
            'duration_minutes' => $expectedDuration,
            'total_questions' => $expectedCount,
            'order' => 1,
            'is_public' => true,
        ]);

        $firstQuestion = null;
        for ($i = 1; $i <= $expectedCount; $i++) {
            $question = Question::create([
                'stem' => "Choose the best answer for item {$i}.",
                'question_type' => Question::TYPE_MCQ,
                'difficulty' => 'medium',
                'section_type' => $sectionType,
                'skill_domain' => $sectionType === Section::TYPE_RW ? 'craft_and_structure' : 'algebra',
                'is_complete' => true,
            ]);
            AnswerChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => 'Answer A', 'is_correct' => true, 'order' => 1]);
            AnswerChoice::create(['question_id' => $question->id, 'label' => 'B', 'content' => 'Answer B', 'is_correct' => false, 'order' => 2]);
            $module->questions()->attach($question->id, ['position' => $i]);
            if ($i === 1) {
                $firstQuestion = $question;
            }
        }

        return [$test, $module, $firstQuestion];
    }
}
