<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'student',
            'email' => 'student@example.com',
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_profile_page_is_accessible_to_authenticated_user(): void
    {
        $response = $this->actingAs($this->user)->get(route('profile'));

        $response->assertStatus(200);
        $response->assertSee('Profile Information');
        $response->assertSee('johndoe');
    }

    public function test_profile_page_is_redirected_to_login_if_unauthenticated(): void
    {
        $response = $this->get(route('profile'));

        $response->assertRedirect(route('signin'));
    }

    public function test_user_can_update_profile_info(): void
    {
        $response = $this->actingAs($this->user)->post(route('profile.update'), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'email' => 'student@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Profile updated successfully.');

        $this->user->refresh();
        $this->assertEquals('Jane Doe', $this->user->name);
        $this->assertEquals('janedoe', $this->user->username);
    }

    public function test_user_can_update_password(): void
    {
        $response = $this->actingAs($this->user)->post(route('profile.update'), [
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'student@example.com',
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Profile updated successfully.');

        $this->user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->user->password));
    }

    public function test_user_cannot_update_password_with_incorrect_current_password(): void
    {
        $response = $this->actingAs($this->user)->post(route('profile.update'), [
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'student@example.com',
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors(['current_password']);
        $this->user->refresh();
        $this->assertTrue(Hash::check('password123', $this->user->password));
    }
}
