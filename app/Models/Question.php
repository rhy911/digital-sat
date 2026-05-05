<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

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
        'external_id',
    ];

    protected $casts = [
        'calculator_allowed' => 'boolean',
        'is_pretest' => 'boolean',
        'is_complete' => 'boolean',
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
