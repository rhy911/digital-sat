<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Classroom extends Model
{
    protected $fillable = ['owner_id', 'name', 'description', 'join_code', 'join_code_rotated_at', 'status'];

    protected function casts(): array
    {
        return ['join_code_rotated_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Classroom $classroom) {
            $classroom->ulid ??= (string) Str::ulid();
            $classroom->join_code ??= self::generateJoinCode();
        });
    }

    public static function generateJoinCode(): string
    {
        do {
            $code = collect(range(1, 8))->map(fn () => Str::upper(Str::random(1)))->implode('');
        } while (self::where('join_code', $code)->exists());

        return $code;
    }

    public function getRouteKeyName(): string { return 'ulid'; }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function coTeachers() { return $this->belongsToMany(User::class, 'classroom_teachers', 'classroom_id', 'teacher_id')->withPivot('added_by')->withTimestamps(); }
    public function memberships() { return $this->hasMany(ClassroomMembership::class); }
    public function activeMemberships() { return $this->memberships()->where('status', 'active'); }
    public function assignments() { return $this->hasMany(Assignment::class); }
    public function documents() { return $this->hasMany(ClassroomDocument::class); }

    public function hasTeacher(User $user): bool
    {
        return (int) $this->owner_id === (int) $user->id
            || $this->coTeachers()->whereKey($user->id)->exists();
    }
}
