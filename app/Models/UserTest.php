<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTest extends Model
{
    protected $fillable = [
        'ulid',
        'user_id',
        'test_id',
        'assignment_id',
        'attempt_type',
        'section_type',
        'attempt_number',
        'score_reading_writing',
        'score_reading_writing_lower',
        'score_reading_writing_upper',
        'score_math',
        'score_math_lower',
        'score_math_upper',
        'total_score',
        'total_score_lower',
        'total_score_upper',
        'score_conversion_set_id',
        'score_conversion_version',
        'score_estimate_kind',
        'status',
        'completed_at',
        'rw_m2_path',
        'math_m2_path',
        'rw_theta',
        'math_theta',
        'rw_theta_se',
        'math_theta_se',
        'scoring_method',
        'current_module_id',
        'current_module_started_at',
        'current_module_elapsed_seconds',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'current_module_started_at' => 'datetime',
        'current_module_id' => 'integer',
        'current_module_elapsed_seconds' => 'integer',
        'attempt_number' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($userTest) {
            if (empty($userTest->ulid)) {
                $userTest->ulid = (string) \Illuminate\Support\Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function test()
    {
        return $this->belongsTo(Test::class)->withTrashed();
    }

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function userAnswers()
    {
        return $this->hasMany(UserTestAnswer::class);
    }

    public function currentModule()
    {
        return $this->belongsTo(Module::class, 'current_module_id');
    }

    public function moduleSubmissions()
    {
        return $this->hasMany(UserTestModuleSubmission::class);
    }

    public function scoreConversionSet()
    {
        return $this->belongsTo(ScoreConversionSet::class);
    }

    public function scoreRevisions()
    {
        return $this->hasMany(UserTestScoreRevision::class);
    }

    /**
     * Scope to attempts a given teacher is allowed to see: assignment-linked
     * attempts in a classroom they own/co-teach, or (if the student opted in)
     * independent practice attempts, still gated by an active shared classroom.
     * Mirrors the assignment-linked condition already enforced in UserTestPolicy::view.
     */
    public function scopeVisibleToTeacher($query, User $teacher)
    {
        return $query->where(function ($outer) use ($teacher) {
            $outer->where(function ($assignmentLinked) use ($teacher) {
                $assignmentLinked->whereNotNull('assignment_id')
                    ->whereHas('assignment.classroom', fn ($classroom) => $classroom
                        ->where('owner_id', $teacher->id)
                        ->orWhereHas('coTeachers', fn ($teachers) => $teachers->whereKey($teacher->id)));
            })->orWhere(function ($sharedIndependent) use ($teacher) {
                $sharedIndependent->whereNull('assignment_id')
                    ->whereHas('user', fn ($student) => $student
                        ->where('share_independent_practice', true)
                        ->whereHas('classroomMemberships', fn ($membership) => $membership
                            ->where('status', 'active')
                            ->whereHas('classroom', fn ($classroom) => $classroom
                                ->where('owner_id', $teacher->id)
                                ->orWhereHas('coTeachers', fn ($teachers) => $teachers->whereKey($teacher->id)))));
            });
        });
    }
}
