<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ClassroomDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'classroom_id',
        'created_by',
        'title',
        'description',
        'source_type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'external_url',
    ];

    protected static function booted(): void
    {
        static::creating(fn (ClassroomDocument $document) => $document->ulid ??= (string) Str::ulid());
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

    public function isFile(): bool
    {
        return $this->source_type === 'file';
    }

    public function displaySize(): ?string
    {
        if (! $this->size_bytes) {
            return null;
        }

        return $this->size_bytes >= 1048576
            ? number_format($this->size_bytes / 1048576, 1).' MB'
            : number_format(max(1, $this->size_bytes / 1024), 0).' KB';
    }
}
