<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $role = fake()->randomElement(['student', 'teacher']);
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'), // Pass mặc định là: password
            'role' => $role,
            'teacher_approval_status' => $role === 'teacher' ? 'approved' : null,
            'is_active' => true,
            'is_2FA_enabled' => false,
        ];
    }

    public function student(): static
    {
        return $this->state(fn () => ['role' => 'student', 'teacher_approval_status' => null]);
    }

    public function teacher(bool $approved = true): static
    {
        return $this->state(fn () => ['role' => 'teacher', 'teacher_approval_status' => $approved ? 'approved' : 'pending']);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin', 'teacher_approval_status' => null]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
        'email_verified_at' => null,
        ]);
    }
}
