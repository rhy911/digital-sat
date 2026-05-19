<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTest extends Model
{
    protected $fillable = [
        'user_id',
        'test_id',
        'score_reading_writing',
        'score_math',
        'total_score',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    public function userAnswers()
    {
        return $this->hasMany(UserTestAnswer::class);
    }
}
