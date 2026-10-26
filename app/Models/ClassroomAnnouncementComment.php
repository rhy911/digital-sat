<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClassroomAnnouncementComment extends Model
{
    protected $fillable = ['announcement_id', 'author_id', 'body'];

    protected static function booted(): void
    {
        static::creating(fn (ClassroomAnnouncementComment $comment) => $comment->ulid ??= (string) Str::ulid());
    }

    /** @return BelongsTo<ClassroomAnnouncement, $this> */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(ClassroomAnnouncement::class, 'announcement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
