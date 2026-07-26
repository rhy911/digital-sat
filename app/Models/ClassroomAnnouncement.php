<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ClassroomAnnouncement extends Model
{
    use SoftDeletes;

    protected $fillable = ['classroom_id', 'author_id', 'body', 'pinned'];

    protected function casts(): array
    {
        return ['pinned' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (ClassroomAnnouncement $announcement) => $announcement->ulid ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function comments()
    {
        return $this->hasMany(ClassroomAnnouncementComment::class, 'announcement_id');
    }
}
