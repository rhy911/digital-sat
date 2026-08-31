<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTestAnswerReview extends Model
{
    public const ERROR_TYPES = [
        'conceptual_gap',
        'misread_question',
        'misread_text_or_data',
        'wrong_strategy',
        'calculation',
        'grammar_rule',
        'elimination',
        'careless',
        'time_pressure',
        'guess',
        'omitted',
    ];

    protected $fillable = [
        'user_test_answer_id',
        'student_error_type',
        'teacher_error_type',
        'teacher_id',
    ];

    public function answer()
    {
        return $this->belongsTo(UserTestAnswer::class, 'user_test_answer_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function effectiveErrorType(?UserTestAnswer $answer = null): string
    {
        return $this->teacher_error_type
            ?? $this->student_error_type
            ?? ($answer && ($answer->selected_answer === null || $answer->selected_answer === '') ? 'omitted' : 'unclassified');
    }
}
