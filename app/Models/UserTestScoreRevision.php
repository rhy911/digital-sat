<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTestScoreRevision extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_test_id', 'run_id', 'reason', 'previous_score', 'revised_score', 'created_at'];

    protected $casts = [
        'previous_score' => 'array',
        'revised_score' => 'array',
        'created_at' => 'datetime',
    ];

    public function userTest()
    {
        return $this->belongsTo(UserTest::class);
    }
}
