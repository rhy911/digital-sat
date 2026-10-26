<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read ?Section $section
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Question> $questions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Section> $sections
 */
class Module extends Model
{
    use SoftDeletes;

    // Digital SAT Official Constants
    const RW_DURATION = 32;
    const RW_QUESTIONS = 27;
    const MATH_DURATION = 35;
    const MATH_QUESTIONS = 22;

    const PRETEST_QUESTIONS_PER_MODULE = 2;

    public const DIFFICULTY_STANDARD = 'standard';
    public const DIFFICULTY_EASY = 'easy';
    public const DIFFICULTY_HARD = 'hard';

    protected $fillable = [
        'section_id',
        'key',
        'module_number',
        'difficulty_level',
        'duration_minutes',
        'total_questions',
        'order',
        'created_by',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param Builder<Module> $query
     * @param ?User $user
     * @return Builder<Module>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (!$user) {
            return $query->where('is_public', true);
        }
        if ($user->role === 'admin') {
            return $query;
        }
        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
              ->orWhere('is_public', true)
              ->orWhereHas('sections', function (Builder $s) use ($user) {
                  /** @var Builder<Section> $s */
                  $s->visibleTo($user);
              });
            if ($user->role === 'student') {
                $q->orWhereHas('sections.test.classrooms', fn ($classrooms) => $classrooms->whereHas(
                    'activeMemberships', fn ($memberships) => $memberships->where('student_id', $user->id)
                ));
            }
        });
    }

    protected static function booted()
    {
        static::creating(function ($module) {
            if (empty($module->ulid)) {
                $module->ulid = (string) \Illuminate\Support\Str::ulid();
            }
        });

        static::created(function ($module) {
            if ($module->section_id) {
                \Illuminate\Support\Facades\DB::table('section_modules')->insertOrIgnore([
                    'section_id' => $module->section_id,
                    'module_id' => $module->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        static::updated(function ($module) {
            if ($module->section_id) {
                \Illuminate\Support\Facades\DB::table('section_modules')->insertOrIgnore([
                    'section_id' => $module->section_id,
                    'module_id' => $module->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id')->withTrashed();
    }

    public function getSectionAttribute(): ?Section
    {
        if ($this->relationLoaded('section')) {
            $relation = $this->relations['section'];
            if ($relation instanceof Section) {
                return $relation;
            }
        } elseif ($this->section_id) {
            $resolved = $this->section()->getResults();
            if ($resolved instanceof Section) {
                return $resolved;
            }
        }

        $fallback = $this->relationLoaded('sections') ? $this->sections->first() : $this->sections()->first();

        return $fallback instanceof Section ? $fallback : null;
    }

    /** @return BelongsToMany<Section, $this> */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'section_modules')->withTrashed();
    }

    /** @return BelongsToMany<Question, $this> */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'module_questions')
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
