<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** @return BelongsTo<ForumThread, $this> */
    public function thread(): BelongsTo { return $this->belongsTo(ForumThread::class, 'thread_id'); }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
