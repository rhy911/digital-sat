<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** @return BelongsTo<Classroom, $this> */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasMany<ClassroomAnnouncementComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ClassroomAnnouncementComment::class, 'announcement_id');
    }
}
