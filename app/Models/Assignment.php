<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property array{state: string, variant: string, completed: \Illuminate\Support\Collection<int, \App\Models\UserTest>, inProgress: ?\App\Models\UserTest, used: int, canStart: bool, maxAllowed: int, hasBonus: bool, bonusAttempts: int}|null $resolved
 * @property-read ?Classroom $classroom
 * @property-read ?Test $test
 * @property-read ?User $teacher
 */
class Assignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'classroom_id',
        'teacher_id',
        'test_id',
        'assign_type',
        'section_type',
        'target_audience',
        'clock_mode',
        'title',
        'instructions',
        'available_at',
        'due_at',
        'attempt_limit',
        'status',
        'published_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return ['available_at' => 'datetime', 'due_at' => 'datetime', 'published_at' => 'datetime', 'closed_at' => 'datetime', 'attempt_limit' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Assignment $assignment) => $assignment->ulid ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string { return 'ulid'; }

    /** @return BelongsTo<Classroom, $this> */
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }

    /** @return BelongsTo<User, $this> */
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }

    /** @return BelongsTo<Test, $this> */
    public function test(): BelongsTo { return $this->belongsTo(Test::class); }

    /** @return HasMany<AssignmentRecipient, $this> */
    public function recipients(): HasMany { return $this->hasMany(AssignmentRecipient::class); }

    /** @return HasMany<UserTest, $this> */
    public function attempts(): HasMany { return $this->hasMany(UserTest::class); }

    public function isTimedExam(): bool
    {
        return ($this->clock_mode ?? 'chained') === 'chained';
    }

    public function isTargeted(): bool
    {
        return ($this->target_audience ?? 'all') === 'specific';
    }

    public function acceptsNewStarts(): bool
    {
        return $this->status === 'published'
            && $this->classroom?->status === 'active'
            && (!$this->available_at || now()->gte($this->available_at))
            && (!$this->due_at || now()->lt($this->due_at));
    }

    public function isSectionAssignment(): bool
    {
        return $this->assign_type === 'section';
    }

    public function sectionLabel(): string
    {
        if (!$this->isSectionAssignment()) {
            return 'Full Test';
        }

        return $this->section_type === 'reading_writing' ? 'Reading & Writing' : 'Math';
    }

    public function sectionShort(): string
    {
        if (!$this->isSectionAssignment()) {
            return 'Full Test';
        }

        return $this->section_type === 'reading_writing' ? 'R&W' : 'Math';
    }

    public function sectionSuffix(): string
    {
        if (!$this->isSectionAssignment()) {
            return '';
        }

        return $this->section_type === 'reading_writing' ? ' (Reading & Writing Only)' : ' (Math Only)';
    }
}
