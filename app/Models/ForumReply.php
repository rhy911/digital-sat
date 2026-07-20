<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ForumReply extends Model
{
    protected $fillable = ['thread_id', 'user_id', 'body'];

    protected static function booted(): void
    {
        static::creating(function (ForumReply $reply) {
            $reply->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string { return 'ulid'; }
    public function thread() { return $this->belongsTo(ForumThread::class, 'thread_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
