<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SprCorrectAnswer extends Model
{
    public $timestamps = false;
    protected $table = 'spr_correct_answers';

    protected $fillable = [
        'question_id',
        'answer',
        'answer_type',
        'tolerance',
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
