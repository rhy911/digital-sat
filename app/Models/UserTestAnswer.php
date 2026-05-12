<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTestAnswer extends Model
{
    protected $fillable = [
        'user_test_id',
        'question_id',
        'selected_answer',
        'is_correct',
    ];

    public function userTest()
    {
        return $this->belongsTo(UserTest::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
