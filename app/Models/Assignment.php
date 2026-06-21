<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Assignment extends Model
{
    protected $fillable = ['classroom_id', 'teacher_id', 'test_id', 'title', 'instructions', 'available_at', 'due_at', 'attempt_limit', 'status', 'published_at', 'closed_at'];

    protected function casts(): array
    {
        return ['available_at' => 'datetime', 'due_at' => 'datetime', 'published_at' => 'datetime', 'closed_at' => 'datetime', 'attempt_limit' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Assignment $assignment) => $assignment->ulid ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string { return 'ulid'; }
    public function classroom() { return $this->belongsTo(Classroom::class); }
    public function teacher() { return $this->belongsTo(User::class, 'teacher_id'); }
    public function test() { return $this->belongsTo(Test::class); }
    public function recipients() { return $this->hasMany(AssignmentRecipient::class); }
    public function attempts() { return $this->hasMany(UserTest::class); }

    public function acceptsNewStarts(): bool
    {
        return $this->status === 'published'
            && $this->classroom?->status === 'active'
            && (!$this->available_at || now()->gte($this->available_at))
            && (!$this->due_at || now()->lt($this->due_at));
    }
}
