<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    public function userTests()
    {
        return $this->hasMany(UserTest::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByCode($query, string $code)
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
