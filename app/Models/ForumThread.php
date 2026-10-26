<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** @return HasMany<ForumReply, $this> */
    public function replies(): HasMany { return $this->hasMany(ForumReply::class, 'thread_id'); }
}
