<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Passage extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'passage_type',
        'word_count',
        'source_title',
        'source_author',
        'source_year',
        'genre',
    ];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
