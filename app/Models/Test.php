<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Test extends Model
{
    use SoftDeletes;

    public const TYPE_FULL = 'full_length';

    public const TYPE_ADAPTIVE_FULL = 'adaptive_full_length';

    public const TYPE_SECTION_ONLY = 'section_only';

    public const TYPE_MODULE_ONLY = 'module_only';

    protected $fillable = [
        'title',
        'description',
        'test_type',
        'total_duration_minutes',
        'break_duration_minutes',
        'status',
        'created_by',
        'is_public',
        'content_locked_at',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'content_locked_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($test) {
            if (empty($test->ulid)) {
                $test->ulid = (string) \Illuminate\Support\Str::ulid();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param Builder<Test> $query
     * @param ?User $user
     * @return Builder<Test>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->where('is_public', true);
        }
        if ($user->role === 'admin') {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('is_public', true)
                ->orWhereHas('shares', fn ($shares) => $shares->where('user_id', $user->id));
            if ($user->role === 'student') {
                $q->orWhereHas('classrooms', fn ($classrooms) => $classrooms->whereHas(
                    'activeMemberships', fn ($memberships) => $memberships->where('student_id', $user->id)
                ));
            }
        });
    }

    /**
     * @param Builder<Test> $query
     * @param User $user
     * @return Builder<Test>
     */
    public function scopeAssignableTo(Builder $query, User $user): Builder
    {
        return $query->where('status', 'active')
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('shares', fn ($shares) => $shares->where('user_id', $user->id));
            });
    }

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('order');
    }

    /** @return HasMany<UserTest, $this> */
    public function userTests(): HasMany
    {
        return $this->hasMany(UserTest::class);
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /** @return BelongsToMany<Classroom, $this> */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_tests');
    }

    public function totalQuestions(): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('module_questions')
            ->join('section_modules', 'section_modules.module_id', '=', 'module_questions.module_id')
            ->join('sections', 'sections.id', '=', 'section_modules.section_id')
            ->join('modules', 'modules.id', '=', 'module_questions.module_id')
            ->join('questions', 'questions.id', '=', 'module_questions.question_id')
            ->where('sections.test_id', $this->id)
            ->whereNull('sections.deleted_at')
            ->whereNull('modules.deleted_at')
            ->whereNull('questions.deleted_at')
            ->distinct()
            ->count('module_questions.question_id');
    }

    /** @return HasMany<TestShare, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(TestShare::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function sharedTeachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'test_shares')
            ->withPivot('shared_by')
            ->withTimestamps();
    }

    public function isContentLocked(): bool
    {
        return $this->assignments()->where('status', 'published')->exists()
            || ($this->test_type === self::TYPE_FULL
                && $this->scoreConversionSets()->where('status', ScoreConversionSet::STATUS_APPROVED)->exists());
    }

    public function syncContentLock(): void
    {
        $test = self::lockForUpdate()->findOrFail($this->id);
        $locked = $test->isContentLocked();

        if ($locked === ($test->content_locked_at !== null)) {
            return;
        }

        $test->forceFill(['content_locked_at' => $locked ? now() : null])->save();
        $this->content_locked_at = $test->content_locked_at;
    }

    public function isPreview(): bool
    {
        return $this->title === 'Test Preview';
    }

    public function isStructurallyComplete(): bool
    {
        $this->loadMissing('sections.modules.questions');

        return $this->sections->isNotEmpty()
            && $this->sections->every(fn ($section) => $section->modules->isNotEmpty()
                && $section->modules->every(fn ($module) => $module->questions->isNotEmpty()));
    }

    /**
     * Recalculate and save the total duration based on modules' durations.
     */
    public function refreshTotalDuration(): int
    {
        $total = 0;

        // Load sections and their modules if not already loaded
        $this->loadMissing('sections.modules');

        foreach ($this->sections as $section) {
            // Sum duration of unique module numbers in this section
            // In Digital SAT, Section 1 has Mod 1 (32m) and Mod 2 (32m)
            // Even if there are multiple versions of Mod 2 (Easy/Hard), they share the same duration.
            $sectionDuration = $section->modules
                ->unique('module_number')
                ->sum('duration_minutes');

            $total += $sectionDuration;
        }

        // Update the stored value
        $this->total_duration_minutes = $total;
        $this->save();

        return $total;
    }

    /** @return HasMany<ScoreConversion, $this> */
    public function scoreConversions(): HasMany
    {
        return $this->hasMany(ScoreConversion::class);
    }

    /** @return HasMany<ScoreConversionSet, $this> */
    public function scoreConversionSets(): HasMany
    {
        return $this->hasMany(ScoreConversionSet::class);
    }

    /** @return HasOne<ScoreConversionSet, $this> */
    public function approvedScoreConversionSet(): HasOne
    {
        return $this->hasOne(ScoreConversionSet::class)
            ->where('status', ScoreConversionSet::STATUS_APPROVED)
            ->latestOfMany('version');
    }
}
