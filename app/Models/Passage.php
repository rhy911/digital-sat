<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ?string $source_title
 * @property ?string $content
 * @property-read ?string $title
 * @property-read ?string $body
 */
class Passage extends Model
{
    protected $fillable = [
        'content',
        'passage_type',
        'word_count',
        'source_title',
        'source_author',
        'source_year',
        'genre',
    ];

    public function getTitleAttribute(): ?string
    {
        return $this->source_title;
    }

    public function getBodyAttribute(): ?string
    {
        return $this->content;
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
