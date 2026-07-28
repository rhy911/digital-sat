<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ClassroomEvent extends Model
{
    use SoftDeletes;

    protected $fillable = ['classroom_id', 'created_by', 'title', 'description', 'starts_at', 'ends_at', 'location', 'all_day'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (ClassroomEvent $event) => $event->ulid ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
