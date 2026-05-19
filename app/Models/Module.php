<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    // Digital SAT Official Constants
    const RW_DURATION = 32;
    const RW_QUESTIONS = 27;
    const MATH_DURATION = 35;
    const MATH_QUESTIONS = 22;

    const PRETEST_QUESTIONS_PER_MODULE = 2;

    protected $fillable = [
        'section_id',
        'key',
        'module_number',
        'difficulty_level',
        'duration_minutes',
        'total_questions',
        'order',
    ];

    protected static function booted()
    {
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
            return $this->belongsTo(Section::class);
        }
        return $this->belongsToMany(Section::class, 'section_modules')->limit(1);
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
        return $this->belongsToMany(Section::class, 'section_modules');
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'module_questions')
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
