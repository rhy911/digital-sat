<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /** @return BelongsTo<UserTest, $this> */
    public function userTest(): BelongsTo
    {
        return $this->belongsTo(UserTest::class);
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** @return BelongsTo<Module, $this> */
    public function issuedNextModule(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'issued_next_module_id');
    }
}
