<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTestModuleSubmission extends Model
{
    protected $fillable = [
        'user_test_id',
        'module_id',
        'issued_next_module_id',
        'result',
        'submitted_at',
    ];

    protected $casts = [
        'result' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function userTest()
    {
        return $this->belongsTo(UserTest::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function issuedNextModule()
    {
        return $this->belongsTo(Module::class, 'issued_next_module_id');
    }
}
