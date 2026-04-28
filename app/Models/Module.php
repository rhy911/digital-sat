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
        'module_number',
        'difficulty_level',
        'duration_minutes',
        'total_questions',
        'order',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'module_questions')
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
