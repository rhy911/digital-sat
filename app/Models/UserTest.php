<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTest extends Model
{
    protected $fillable = [
        'ulid',
        'user_id',
        'test_id',
        'assignment_id',
        'attempt_number',
        'score_reading_writing',
        'score_math',
        'total_score',
        'status',
        'completed_at',
        'rw_m2_path',
        'math_m2_path',
        'rw_theta',
        'math_theta',
        'current_module_id',
        'current_module_started_at',
        'current_module_elapsed_seconds',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'current_module_started_at' => 'datetime',
        'current_module_id' => 'integer',
        'current_module_elapsed_seconds' => 'integer',
        'attempt_number' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($userTest) {
            if (empty($userTest->ulid)) {
                $userTest->ulid = (string) \Illuminate\Support\Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    public function assignment() { return $this->belongsTo(Assignment::class); }

    public function userAnswers()
    {
        return $this->hasMany(UserTestAnswer::class);
    }

    public function currentModule()
    {
        return $this->belongsTo(Module::class, 'current_module_id');
    }
}
