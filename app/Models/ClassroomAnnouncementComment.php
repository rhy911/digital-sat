<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClassroomAnnouncementComment extends Model
{
    protected $fillable = ['announcement_id', 'author_id', 'body'];

    protected static function booted(): void
    {
        static::creating(fn (ClassroomAnnouncementComment $comment) => $comment->ulid ??= (string) Str::ulid());
    }

    public function announcement()
    {
        return $this->belongsTo(ClassroomAnnouncement::class, 'announcement_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
