<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
            'email' => 'teacher@bluebook.com',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Test student login page accepts student credentials.
     */
    public function test_student_login_accepts_student(): void
    {
        $response = $this->postJson(route('signin'), [
            'email' => 'student@bluebook.com',
            'password' => 'password',
            'role' => 'student',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'message' => 'Đăng nhập thành công.',
        ]);
        $this->assertAuthenticatedAs($this->student);
    }

    public function test_student_login_accepts_checked_remember_box(): void
    {
        $response = $this->postJson(route('signin'), [
            'email' => 'student@bluebook.com',
            'password' => 'password',
            'role' => 'student',
            'remember' => '1',
        ]);

        $response->assertStatus(200);
        $response->assertCookie(Auth::getRecallerName());
        $this->assertAuthenticatedAs($this->student);
    }

    public function test_student_login_accepts_browser_default_remember_value(): void
    {
        $response = $this->postJson(route('signin'), [
            'email' => 'student@bluebook.com',
            'password' => 'password',
            'role' => 'student',
            'remember' => 'on',
        ]);

        $response->assertStatus(200);
        $response->assertCookie(Auth::getRecallerName());
        $this->assertAuthenticatedAs($this->student);
    }

    /**
     * Test student login page rejects teacher credentials.
     */
    public function test_student_login_rejects_teacher(): void
    {
        $response = $this->postJson(route('signin'), [
            'email' => 'teacher@bluebook.com',
            'password' => 'password',
            'role' => 'student',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'This account is registered as a teacher.',
            $response->json('message')
        );
        $this->assertStringContainsString(
            'href="' . route('signin.form', ['role' => 'teacher']) . '"',
            $response->json('message')
        );
        $this->assertGuest();
    }

    /**
     * Test teacher login page accepts teacher credentials.
     */
    public function test_teacher_login_accepts_teacher(): void
    {
        $response = $this->postJson(route('signin'), [
            'email' => 'teacher@bluebook.com',
            'password' => 'password',
            'role' => 'teacher',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'message' => 'Đăng nhập thành công.',
        ]);
        $this->assertAuthenticatedAs($this->teacher);
    }

    /**
     * Test teacher login page rejects student credentials.
     */
    public function test_teacher_login_rejects_student(): void
    {
        $response = $this->postJson(route('signin'), [
            'email' => 'student@bluebook.com',
            'password' => 'password',
            'role' => 'teacher',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'This account is registered as a student.',
            $response->json('message')
        );
        $this->assertStringContainsString(
            'href="' . route('signin.form', ['role' => 'student']) . '"',
            $response->json('message')
        );
        $this->assertGuest();
    }
}
