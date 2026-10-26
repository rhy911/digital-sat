<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property ?\Illuminate\Support\Carbon $expires_at
 * @property-read ?Test $test
 * @property-read ?User $teacher
 */
class ExamSession extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'teacher_id',
        'test_id',
        'title',
        'status',
        'expires_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExamSession $session) {
            $session->ulid ??= (string) Str::ulid();
            $session->code ??= self::generateUniqueCode();
        });
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = collect(range(1, 6))->map(fn () => Str::upper(Str::random(1)))->implode('');
        } while (self::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return BelongsTo<User, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** @return BelongsTo<Test, $this> */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    /** @return HasMany<UserTest, $this> */
    public function userTests(): HasMany
    {
        return $this->hasMany(UserTest::class);
    }

    /**
     * @param Builder<ExamSession> $query
     * @return Builder<ExamSession>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @param Builder<ExamSession> $query
     * @return Builder<ExamSession>
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', Str::upper($code));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function canJoin(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }
}
