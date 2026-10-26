<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'name',
        'email',
        'password',
        'is_2FA_enabled',
        'two_factor_code',
        'two_factor_expired_at',
        'is_active',
        'avatar',
        'teacher_approval_status',
        'teacher_reviewed_by',
        'teacher_reviewed_at',
        'teacher_rejection_reason',
        'share_independent_practice',
        'is_guest',
        'target_score',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'is_2FA_enabled' => 'boolean',
            'is_active' => 'boolean',
            'is_guest' => 'boolean',
            'share_independent_practice' => 'boolean',
            'two_factor_expired_at' => 'datetime',
            'teacher_reviewed_at' => 'datetime',
            'target_score' => 'integer',
            'home_preview_opened_at' => 'datetime',
            'home_report_opened_at' => 'datetime',
        ];
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /** @return HasMany<UserTest, $this> */
    public function userTests(): HasMany
    {
        return $this->hasMany(UserTest::class);
    }

    public function getInitialsAttribute(): string
    {
        $initials = collect(explode(' ', trim($this->name ?? $this->username ?? '')))
            ->filter()
            ->take(2)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'U';
    }

    /** @return HasMany<Classroom, $this> */
    public function ownedClassrooms(): HasMany { return $this->hasMany(Classroom::class, 'owner_id'); }

    /** @return BelongsToMany<Classroom, $this> */
    public function coTaughtClassrooms(): BelongsToMany { return $this->belongsToMany(Classroom::class, 'classroom_teachers', 'teacher_id', 'classroom_id')->withPivot('added_by')->withTimestamps(); }

    /** @return HasMany<ClassroomMembership, $this> */
    public function classroomMemberships(): HasMany { return $this->hasMany(ClassroomMembership::class, 'student_id'); }

    /** @return HasMany<AssignmentRecipient, $this> */
    public function assignmentRecipients(): HasMany { return $this->hasMany(AssignmentRecipient::class, 'student_id'); }

    /** @return BelongsToMany<Test, $this> */
    public function sharedTests(): BelongsToMany { return $this->belongsToMany(Test::class, 'test_shares')->withTimestamps(); }

    public function isApprovedTeacher(): bool { return $this->role === 'teacher' && $this->teacher_approval_status === 'approved'; }

    /** @return HasMany<ExamSession, $this> */
    public function examSessions(): HasMany { return $this->hasMany(ExamSession::class, 'teacher_id'); }
}
