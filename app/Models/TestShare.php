<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestShare extends Model
{
    protected $fillable = [
        'test_id',
        'user_id',
        'shared_by',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sharer()
    {
        return $this->belongsTo(User::class, 'shared_by');
    }
}
