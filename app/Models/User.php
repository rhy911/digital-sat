<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\ResetPasswordNotification;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    // Traits: HasApiTokens (Sanctum), HasFactory, Notifiable, SoftDeletes
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
        'is_2FA_enabled',
        'two_factor_code',
        'two_factor_expired_at',
        'is_active',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        // Hide two_factor_code from JSON responses for security reasons
        'two_factor_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Cast properties to ensure correct data types
            'is_active' => 'boolean',
            'is_2FA_enabled' => 'boolean',
            'two_factor_expired_at' => 'datetime',
        ];
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function userTests()
    {
        return $this->hasMany(UserTest::class);
    }
}
