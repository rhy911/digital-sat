<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    public const TYPE_MCQ = 'multiple_choice';
    public const TYPE_SPR = 'student_produced_response';

    protected static function booted()
    {
        static::creating(function ($question) {
            // Automatically assign standardized March 2026 3PL IRT parameters if not explicitly provided or if default-matched
            if ($question->irt_a === null || $question->irt_a == 0.90) {
                $question->irt_a = $question->question_type === self::TYPE_SPR ? 1.3 : 0.9;
            }
            if ($question->irt_c === null || $question->irt_c == 0.25) {
                $question->irt_c = $question->question_type === self::TYPE_SPR ? 0.0 : 0.25;
            }
            if ($question->irt_b === null || $question->irt_b == 0.00) {
                $question->irt_b = match ($question->difficulty) {
                    'easy' => -1.2,
                    'hard' => 1.4,
                    default => 0.0,
                };
            }
        });
    }

    protected $fillable = [
        'passage_id',
        'paired_passage_id',
        'stem',
        'question_type',
        'difficulty',
        'is_pretest',
        'is_complete',
        'section_type',
        'skill_domain',
        'skill_subdomain',
        'spr_hint',
        'calculator_allowed',
        'irt_a',
        'irt_b',
        'irt_c',
        'external_id',
    ];

    protected $casts = [
        'calculator_allowed' => 'boolean',
        'is_pretest' => 'boolean',
        'is_complete' => 'boolean',
        'irt_a' => 'float',
        'irt_b' => 'float',
        'irt_c' => 'float',
    ];

    public function passage()
    {
        return $this->belongsTo(Passage::class);
    }

    public function answerChoices()
    {
        return $this->hasMany(AnswerChoice::class);
    }

    public function explanation()
    {
        return $this->hasOne(QuestionExplanation::class);
    }

    public function media()
    {
        return $this->hasMany(QuestionMedia::class);
    }

    public function sprCorrectAnswers()
    {
        return $this->hasMany(SprCorrectAnswer::class);
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'module_questions')
            ->withPivot('position');
    }
}
