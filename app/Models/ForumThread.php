<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ForumThread extends Model
{
    protected $fillable = ['user_id', 'title', 'body', 'category'];

    protected static function booted(): void
    {
        static::creating(function (ForumThread $thread) {
            $thread->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string { return 'ulid'; }
    public function user() { return $this->belongsTo(User::class); }
    public function replies() { return $this->hasMany(ForumReply::class, 'thread_id'); }
}
