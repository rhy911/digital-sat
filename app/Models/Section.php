<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_id',
        'name',
        'type',
        'order',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'section_modules')
            ->orderBy('module_number')
            ->orderBy('order');
    }
}
