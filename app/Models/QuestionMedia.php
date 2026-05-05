<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
