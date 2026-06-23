<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoreConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'score_conversion_set_id',
        'test_id',
        'section_type',
        'm2_difficulty',
        'raw_score',
        'scaled_score',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    public function conversionSet()
    {
        return $this->belongsTo(ScoreConversionSet::class, 'score_conversion_set_id');
    }
}
