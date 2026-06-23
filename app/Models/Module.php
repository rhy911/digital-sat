<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use HasFactory, SoftDeletes;

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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo($query, $user)
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
              ->orWhereHas('sections', function ($s) use ($user) {
                  $s->visibleTo($user);
              });
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

    public function section()
    {
        // For backwards compatibility, return the primary belongsTo relation
        // or fall back to the first linked section via many-to-many
        if ($this->section_id) {
            return $this->belongsTo(Section::class)->withTrashed();
        }
        return $this->belongsToMany(Section::class, 'section_modules')->withTrashed()->limit(1);
    }

    public function getSectionAttribute()
    {
        if ($this->relationLoaded('section')) {
            $relation = $this->relations['section'];
            if ($relation instanceof \Illuminate\Database\Eloquent\Collection) {
                return $relation->first();
            }
            return $relation;
        }

        if ($this->section_id) {
            return $this->belongsTo(Section::class)->getResults();
        }

        return $this->sections()->first();
    }

    public function sections()
    {
        return $this->belongsToMany(Section::class, 'section_modules')->withTrashed();
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'module_questions')
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
