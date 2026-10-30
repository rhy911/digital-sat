<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create([
            'role' => 'student',
            'email' => 'student@bluebook.com',
            'email_verified_at' => now(),
        ]);

        $this->teacher = User::factory()->create([
            'role' => 'teacher',
            'teacher_approval_status' => 'approved',
            'email' => 'teacher@bluebook.com',
            'email_verified_at' => now(),
        ]);
    }

    public function test_signin_page_is_unified_and_legacy_url_redirects(): void
    {
        $this->get(route('signin'))
            ->assertOk()
            ->assertSee('<h1>Sign in</h1>', false)
            ->assertDontSee('Sign in as');

        $this->get(route('signin.form', ['role' => 'teacher']))
            ->assertRedirect(route('signin'));
    }

    public function test_student_can_sign_in_even_when_legacy_role_differs(): void
    {
        $response = $this->postJson(route('signin'), [
            'email' => 'student@bluebook.com',
            'password' => 'password',
            'role' => 'teacher',
        ]);

        $response->assertOk()->assertJsonPath('redirect', route('dashboard'));
        $this->assertAuthenticatedAs($this->student);
    }

    public function test_teacher_can_sign_in_even_when_legacy_role_differs(): void
    {
        $response = $this->postJson(route('signin'), [
            'email' => 'teacher@bluebook.com',
            'password' => 'password',
            'role' => 'student',
        ]);

        $response->assertOk()->assertJsonPath('redirect', route('dashboard'));
        $this->assertAuthenticatedAs($this->teacher);
    }

    public function test_signin_keeps_remember_cookie(): void
    {
        $this->postJson(route('signin'), [
            'email' => 'student@bluebook.com',
            'password' => 'password',
            'remember' => 'on',
        ])->assertOk()->assertCookie(Auth::getRecallerName());

        $this->assertAuthenticatedAs($this->student);
    }

    public function test_pending_teacher_signin_uses_dashboard_gate(): void
    {
        $pendingTeacher = User::factory()->create([
            'role' => 'teacher',
            'teacher_approval_status' => 'pending',
            'email' => 'pending@bluebook.com',
            'email_verified_at' => now(),
        ]);

        $this->postJson(route('signin'), [
            'email' => 'pending@bluebook.com',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('redirect', route('dashboard'));

        $this->assertAuthenticatedAs($pendingTeacher);
    }

    public function test_authenticated_landing_visit_enters_dashboard(): void
    {
        $this->actingAs($this->student)->get(route('landing'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_signup_honors_teacher_entry_and_renders_one_form(): void
    {
        $this->get(route('signup', ['role' => 'teacher']))
            ->assertOk()
            ->assertSee('id="signupForm"', false)
            ->assertSee('id="roleTeacher"', false)
            ->assertSee('value="teacher"', false)
            ->assertSee('checked', false)
            ->assertDontSee('id="step1"', false);
    }

    public function test_signup_defaults_to_student(): void
    {
        $response = $this->get(route('signup'))->assertOk();

        $this->assertMatchesRegularExpression('/<input[^>]*id="roleStudent"[^>]*checked/s', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="roleTeacher"[^>]*checked/s', $response->getContent());
    }

    public function test_json_validation_response_has_field_errors(): void
    {
        $this->postJson(route('signin'), [
            'email' => 'not-an-email',
            'password' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_html_validation_returns_to_signin_with_field_errors(): void
    {
        $this->from(route('signin'))
            ->post(route('signin'), ['email' => 'invalid', 'password' => ''])
            ->assertRedirect(route('signin'))
            ->assertSessionHasErrors(['email', 'password']);

        $this->get(route('signin'))
            ->assertOk()
            ->assertSee('The email field must be a valid email address.')
            ->assertSee('The password field is required.');
    }

    public function test_forgot_password_uses_generic_success_copy(): void
    {
        Notification::fake();

        $this->postJson(route('forgot'), ['email' => $this->student->email])
            ->assertOk()
            ->assertJsonPath('message', 'If that email address is registered, a password reset link will be sent.');

        Notification::assertSentTo($this->student, ResetPasswordNotification::class);
    }

    public function test_signup_delivery_failure_keeps_a_truthful_notice_after_json_redirect(): void
    {
        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('mail transport unavailable'));

        $this->postJson(route('signup'), [
            'username' => 'newstudent',
            'email' => 'newstudent@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()
            ->assertJsonPath('redirect', route('verify.email.notice'))
            ->assertJsonPath('message', 'Your account has been created, but we could not deliver your verification email. Please try resending it.');

        $this->get(route('verify.email.notice'))
            ->assertOk()
            ->assertSee('could not deliver your verification email')
            ->assertSee('Verify your email to finish setting up your account.')
            ->assertDontSee('Check your inbox for a verification link.');
    }

    public function test_password_reset_redirects_to_unified_signin_with_flash(): void
    {
        $token = Password::createToken($this->student);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $this->student->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('signin'))
            ->assertSessionHas('success', 'Your password has been reset. Sign in with your new password.');

        $this->assertTrue(password_verify('new-password', $this->student->fresh()->password));
        $this->get(route('signin'))->assertSee('Your password has been reset. Sign in with your new password.');
    }

    public function test_verification_resend_succeeds_then_enforces_server_cooldown(): void
    {
        Notification::fake();
        $unverified = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => null,
        ]);
        $this->actingAs($unverified);

        $this->postJson(route('verification.send'))
            ->assertOk()
            ->assertJsonPath('cooldown', 60);
        Notification::assertSentTo($unverified, VerifyEmailNotification::class);

        $this->postJson(route('verification.send'))
            ->assertTooManyRequests()
            ->assertJsonStructure(['message', 'cooldown']);
    }
}
