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
    public function exclusiveTests()
    {
        return $this->belongsToMany(Test::class, 'classroom_tests')
            ->withPivot('unit_name', 'order')
            ->withTimestamps();
    }
    public function note() { return $this->hasOne(ClassroomNote::class); }
    public function announcements() { return $this->hasMany(ClassroomAnnouncement::class); }

    /**
     * Group documents and exclusive tests into structured study units.
     *
     * @return array<string, array{title: string, documents: \Illuminate\Support\Collection, tests: \Illuminate\Support\Collection}>
     */
    public function studyUnits(): array
    {
        $docs = $this->documents->sortBy('order');
        $tests = $this->exclusiveTests->sortBy('pivot.order');

        $unitNames = $docs->pluck('unit_name')->merge($tests->pluck('pivot.unit_name'))->filter()->unique()->values();
        $units = [];

        foreach ($unitNames as $name) {
            $units[$name] = [
                'title' => $name,
                'documents' => $docs->where('unit_name', $name)->values(),
                'tests' => $tests->where('pivot.unit_name', $name)->values(),
            ];
        }

        $generalDocs = $docs->whereNull('unit_name')->values();
        $generalTests = $tests->whereNull('pivot.unit_name')->values();

        if ($generalDocs->isNotEmpty() || $generalTests->isNotEmpty() || empty($units)) {
            $units['_general'] = [
                'title' => __('classroom.general_resources') ?: 'General Materials',
                'documents' => $generalDocs,
                'tests' => $generalTests,
            ];
        }

        return $units;
    }

    public function hasTeacher(User $user): bool
    {
        return (int) $this->owner_id === (int) $user->id
            || $this->coTeachers()->whereKey($user->id)->exists();
    }
}
