<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read ?User $user
 * @property-read ?Test $test
 * @property-read ?Assignment $assignment
 * @property-read ?ExamSession $examSession
 * @property-read ?Module $currentModule
 * @property-read ?ScoreConversionSet $scoreConversionSet
 * @property-read ?User $feedbackAuthor
 * @property ?Carbon $guest_trial_expires_at
 * @property ?Carbon $guest_trial_absolute_expires_at
 */
class UserTest extends Model
{
    protected $fillable = [
        'ulid',
        'user_id',
        'test_id',
        'assignment_id',
        'exam_session_id',
        'guest_name',
        'is_public_trial',
        'guest_trial_expires_at',
        'guest_trial_absolute_expires_at',
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
        'current_module_paused',
        'attempt_controller_token_hash',
        'attempt_controller_last_seen_at',
        'teacher_feedback',
        'feedback_at',
        'feedback_by',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'feedback_at' => 'datetime',
        'current_module_started_at' => 'datetime',
        'current_module_id' => 'integer',
        'current_module_elapsed_seconds' => 'integer',
        'current_module_paused' => 'boolean',
        'attempt_controller_last_seen_at' => 'datetime',
        'attempt_number' => 'integer',
        'is_public_trial' => 'boolean',
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

    public function isEligibleGuestPublicTrial(): bool
    {
        return $this->is_public_trial
            && $this->assignment_id === null
            && $this->exam_session_id === null
            && (bool) $this->user()->withTrashed()->value('is_guest');
    }

    /** @return Attribute<Carbon|null, Carbon|string|null> */
    protected function guestTrialExpiresAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone')),
            set: fn ($value) => $value === null ? null : Carbon::parse($value)->utc()->format('Y-m-d H:i:s'),
        );
    }

    /** @return Attribute<Carbon|null, Carbon|string|null> */
    protected function guestTrialAbsoluteExpiresAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone')),
            set: fn ($value) => $value === null ? null : Carbon::parse($value)->utc()->format('Y-m-d H:i:s'),
        );
    }

    public function isGuestTrialExpired(): bool
    {
        return $this->isEligibleGuestPublicTrial() && (
            ($this->guest_trial_expires_at !== null && $this->guest_trial_expires_at->lessThanOrEqualTo(now()))
            || ($this->guest_trial_absolute_expires_at !== null && $this->guest_trial_absolute_expires_at->lessThanOrEqualTo(now()))
        );
    }

    public function renewGuestTrialActivity(): bool
    {
        $absolute = $this->guest_trial_absolute_expires_at;
        if ($absolute === null) {
            return false;
        }
        $now = now()->utc();
        $deadline = $now->copy()->addMinutes((int) config('public_trial.retention.idle_minutes', 30));
        $deadline = $deadline->min($absolute->copy()->utc());

        $renewed = \Illuminate\Support\Facades\DB::table('user_tests')
            ->where('id', $this->id)
            ->where('is_public_trial', true)
            ->whereNull('assignment_id')
            ->whereNull('exam_session_id')
            ->whereNotNull('guest_trial_expires_at')
            ->where('guest_trial_expires_at', '>', $now)
            ->where('guest_trial_absolute_expires_at', '>', $now)
            ->whereExists(fn ($owner) => $owner->selectRaw('1')->from('users')
                ->whereColumn('users.id', 'user_tests.user_id')->where('users.is_guest', true))
            ->update(['guest_trial_expires_at' => \Illuminate\Support\Facades\DB::raw(
                "LEAST(guest_trial_absolute_expires_at, GREATEST(guest_trial_expires_at, '{$deadline->format('Y-m-d H:i:s')}'))"
            )]);

        if ($renewed) {
            $this->guest_trial_expires_at = $deadline;
        }

        return (bool) $renewed;
    }

    /**
     * @param Builder<UserTest> $query
     * @return Builder<UserTest>
     */
    public function scopeEligibleGuestPublicTrials(Builder $query): Builder
    {
        return $query->where('is_public_trial', true)
            ->whereNull('assignment_id')
            ->whereNull('exam_session_id')
            ->whereIn('user_id', User::withTrashed()->where('is_guest', true)->select('id'));
    }

    /**
     * @param Builder<UserTest> $query
     * @return Builder<UserTest>
     */
    public function scopeExpiredGuestPublicTrials(Builder $query): Builder
    {
        return $query->eligibleGuestPublicTrials()->whereNotNull('guest_trial_expires_at')
            ->where(function (Builder $expired) {
                $expired->where('guest_trial_expires_at', '<=', now()->utc())
                    ->orWhere('guest_trial_absolute_expires_at', '<=', now()->utc());
            });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Test, $this> */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class)->withTrashed();
    }

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** @return BelongsTo<ExamSession, $this> */
    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    /**
     * Whether this attempt runs on a fixed, non-pausable clock — chained
     * module deadlines, server-side auto-submit sweep (see
     * AttemptEngine) — rather than practice-style
     * pause/resume timing. True for classroom assignments AND standalone
     * exam-session attempts: a candidate walking away from a live exam must
     * not be able to stop the clock by closing the tab, same as an
     * assignment. Single source of truth for every `assignment_id`-keyed
     * timing branch in SessionController, AttemptEngine,
     * SubmissionController, AnswerController and the timeout sweep.
     */
    public function runsOnChainedClock(): bool
    {
        if ($this->assignment && ($this->assignment->clock_mode ?? 'chained') === 'paused') {
            return false;
        }

        return (bool) ($this->assignment_id || $this->exam_session_id);
    }

    /** @return BelongsTo<User, $this> */
    public function feedbackAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'feedback_by');
    }

    /** @return HasMany<UserTestAnswer, $this> */
    public function userAnswers(): HasMany
    {
        return $this->hasMany(UserTestAnswer::class);
    }

    /** @return BelongsTo<Module, $this> */
    public function currentModule(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'current_module_id');
    }

    /** @return HasMany<UserTestModuleSubmission, $this> */
    public function moduleSubmissions(): HasMany
    {
        return $this->hasMany(UserTestModuleSubmission::class);
    }

    /** @return BelongsTo<ScoreConversionSet, $this> */
    public function scoreConversionSet(): BelongsTo
    {
        return $this->belongsTo(ScoreConversionSet::class);
    }

    /** @return HasMany<UserTestScoreRevision, $this> */
    public function scoreRevisions(): HasMany
    {
        return $this->hasMany(UserTestScoreRevision::class);
    }

    /**
     * Scope out section attempts that have already been absorbed into a merged
     * full attempt (see AttemptEngine::autoMergeIfEligible). The merge
     * only deletes the leftover attempt when it is not assignment-linked, so an
     * assignment-linked section attempt survives and would otherwise show up
     * next to the merged score in student-facing lists.
     */
    /**
     * @param Builder<UserTest> $query
     * @return Builder<UserTest>
     */
    public function scopeExcludingAbsorbedSections(Builder $query): Builder
    {
        return $query->where(function ($outer) {
            $outer->where('attempt_type', '!=', 'section')
                ->orWhereNotExists(function ($merged) {
                    $merged->selectRaw('1')
                        ->from('user_tests as merged_attempt')
                        ->whereColumn('merged_attempt.user_id', 'user_tests.user_id')
                        ->whereColumn('merged_attempt.test_id', 'user_tests.test_id')
                        ->whereColumn('merged_attempt.id', '!=', 'user_tests.id')
                        ->where('merged_attempt.attempt_type', 'full')
                        ->where('merged_attempt.status', 'completed')
                        ->whereNotNull('merged_attempt.score_reading_writing')
                        ->whereNotNull('merged_attempt.score_math')
                        ->whereColumn('merged_attempt.completed_at', '>=', 'user_tests.completed_at');
                });
        });
    }

    /**
     * @param  Builder<UserTest>  $query
     * @return Builder<UserTest>
     */
    public function scopeVisibleInGuestAnalytics(Builder $query, User $viewer): Builder
    {
        if (! $viewer->is_guest) {
            return $query;
        }

        return $query->where('is_public_trial', false);
    }

    /**
     * Scope to completed public trial attempts owned by a given user.
     *
     * @param  Builder<UserTest>  $query
     * @return Builder<UserTest>
     */
    public function scopeOwnedCompletedPublicTrial(Builder $query, int|string $userId, ?string $ulid = null): Builder
    {
        $query->where('user_id', $userId)
            ->where('status', 'completed')
            ->where('is_public_trial', true);

        if ($ulid !== null) {
            $query->where('ulid', $ulid);
        }

        return $query;
    }

    /**
     * Scope to attempts a given teacher is allowed to see: assignment-linked
     * attempts in a classroom they own/co-teach, or (if the student opted in)
     * independent practice attempts, still gated by an active shared classroom.
     * Mirrors the assignment-linked condition already enforced in UserTestPolicy::view.
     */
    /**
     * @param Builder<UserTest> $query
     * @param User $teacher
     * @return Builder<UserTest>
     */
    public function scopeVisibleToTeacher(Builder $query, User $teacher): Builder
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
