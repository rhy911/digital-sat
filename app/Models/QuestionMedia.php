<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionMedia extends Model
{
    protected $table = 'question_media';

    protected $fillable = [
        'question_id',
        'media_type',
        'file_path',
        'alt_text',
        'position',
        'order',
        'width',
        'height',
    ];

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
