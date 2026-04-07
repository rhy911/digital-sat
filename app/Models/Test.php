<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Test extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'test_type',
        'total_duration_minutes',
        'break_duration_minutes',
        'status',
    ];

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function scoreConversions()
    {
        return $this->hasMany(ScoreConversion::class);
    }
}
