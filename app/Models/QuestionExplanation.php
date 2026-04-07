<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionExplanation extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'explanation',
        'rationale_a',
        'rationale_b',
        'rationale_c',
        'rationale_d',
        'strategy_tip',
        'common_mistakes',
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
