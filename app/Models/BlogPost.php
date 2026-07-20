<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    protected $fillable = ['teacher_id', 'title', 'excerpt', 'body', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (BlogPost $post) {
            $post->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string { return 'ulid'; }
    public function teacher() { return $this->belongsTo(User::class, 'teacher_id'); }
}
