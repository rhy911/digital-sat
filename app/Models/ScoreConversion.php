<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreConversion extends Model
{
    protected $fillable = [
        'score_conversion_set_id',
        'test_id',
        'section_type',
        'm2_difficulty',
        'raw_score',
        'scaled_score',
    ];

    /** @return BelongsTo<Test, $this> */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    /** @return BelongsTo<ScoreConversionSet, $this> */
    public function conversionSet(): BelongsTo
    {
        return $this->belongsTo(ScoreConversionSet::class, 'score_conversion_set_id');
    }
}
