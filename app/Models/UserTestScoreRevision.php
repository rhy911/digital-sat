<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTestScoreRevision extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_test_id', 'run_id', 'reason', 'previous_score', 'revised_score', 'created_at'];

    protected $casts = [
        'previous_score' => 'array',
        'revised_score' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<UserTest, $this> */
    public function userTest(): BelongsTo
    {
        return $this->belongsTo(UserTest::class);
    }
}
