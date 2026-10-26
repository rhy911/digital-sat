<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionExplanation extends Model
{
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

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
