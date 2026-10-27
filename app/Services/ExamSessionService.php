<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\Module;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Models\UserTestModuleSubmission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Standalone / free exam-taking sessions: a teacher hosts a live attempt at a
 * test outside the classroom/assignment model, and candidates (guest or
 * signed-in) join with a 6-character code. Guest identity is a real `User`
 * row (`is_guest = true`) so it flows through the existing engine controllers,
 * ownership checks, and IRT scoring unmodified.
 */
class ExamSessionService
{
    public function __construct(
        private AttemptEngine $engine,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $teacher, array $data): ExamSession
    {
        $test = Test::assignableTo($teacher)->findOrFail($data['test_id']);

        return ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => $data['title'],
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }

    public function findJoinable(string $code): ExamSession
    {
        $session = ExamSession::byCode($code)->with('test')->firstOrFail();
        abort_unless($session->canJoin(), 409, 'This exam session is not currently open.');

        return $session;
    }

    /**
     * Look up a hand-typed code without aborting.
     *
     * findJoinable() is for a code that arrived in the URL — a bad one there is
     * a broken link and a 404/409 page is the honest answer. A code typed into
     * a form is different: the expected failure is a typo, so the caller needs
     * to put a message back on the field rather than throw the candidate onto
     * an error page and lose what they entered.
     *
     * @return array{0: ?ExamSession, 1: ?string} the session, or null plus why not
     */
    public function resolveTypedCode(string $code): array
    {
        $session = ExamSession::byCode(trim($code))->with('test')->first();

        if (! $session) {
            return [null, 'We could not find an exam with that code. Check it and try again.'];
        }

        if ($session->isExpired()) {
            return [null, 'That exam session has expired.'];
        }

        return match ($session->status) {
            ExamSession::STATUS_ACTIVE => [$session, null],
            ExamSession::STATUS_PAUSED => [null, 'That exam session is paused. Wait for your teacher to reopen it.'],
            default => [null, 'That exam session has closed.'],
        };
    }

    /**
     * A synthetic User row is the whole guest strategy: it lets a guest flow
     * through Auth::login(), the engine's ownership-scoped queries, and IRT
     * scoring with zero special-casing. email/username are unique-but-inert
     * (guests never sign in with them); email_verified_at is set immediately
     * so EnsureEmailIsVerified never blocks them on routes that carry it.
     */
    public function provisionGuest(string $displayName): User
    {
        $displayName = trim($displayName) !== '' ? trim($displayName) : 'Guest';

        $user = User::create([
            'name' => $displayName,
            'username' => 'guest_'.Str::lower(Str::random(16)),
            'email' => 'guest_'.Str::lower(Str::random(20)).'@guest.examsession.local',
            'password' => Hash::make(Str::random(32)),
            'is_guest' => true,
        ]);
        $user->role = 'student';
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    public function startOrResumeAttempt(ExamSession $session, User $user, ?string $guestName = null): UserTest
    {
        return DB::transaction(function () use ($session, $user, $guestName) {
            User::where('id', $user->id)->lockForUpdate()->first();

            // Scoped by (user, test), not (user, session): the DB enforces at most
            // one in-progress, non-assignment attempt per (test_id, user_id) via
            // uq_user_test_active_practice, so a signed-in user who already has an
            // in-progress attempt on this exact test — independent practice, or a
            // different exam session — collides with that constraint otherwise.
            $existing = UserTest::where('user_id', $user->id)
                ->where('test_id', $session->test_id)
                ->where('status', 'in_progress')
                ->whereNull('assignment_id')
                ->latest('updated_at')
                ->first();

            if ($existing && (int) $existing->exam_session_id === (int) $session->id) {
                $this->engine->issueInitialModule($existing, $session->test);

                return $existing;
            }

            if ($existing) {
                // Same precedent as AttemptController::startTest's 'fresh' mode:
                // the newly joined session wins, the stale attempt is abandoned.
                $existing->update(['status' => 'abandoned']);
            }

            $attempt = UserTest::create([
                'user_id' => $user->id,
                'test_id' => $session->test_id,
                'exam_session_id' => $session->id,
                'guest_name' => $guestName,
                'status' => 'in_progress',
            ]);

            $this->engine->issueInitialModule($attempt, $session->test);

            return $attempt;
        });
    }

    /**
     * A guest who signs up or signs in after finishing a free exam session
     * keeps their result: every `UserTest` still owned by the guest `User`
     * row is reassigned to the real account, then the guest row is soft
     * deleted (never hard deleted — a still-`in_progress` attempt that could
     * not be transferred, see below, must keep a valid `user_id` to point
     * at). Triggered from Auth\RegisterController / Auth\LoginController via
     * a guest id stashed in the session by
     * GuestExamController::beginAccountLink() — see that method for why the
     * stash is trustworthy (it is never read from client input).
     *
     * @return int number of attempts actually reassigned
     */
    public function mergeGuestAccount(int $guestUserId, User $realUser): int
    {
        if ((int) $guestUserId === (int) $realUser->id) {
            return 0;
        }

        return DB::transaction(function () use ($guestUserId, $realUser) {
            $guest = User::whereKey($guestUserId)->where('is_guest', true)->lockForUpdate()->first();
            if (! $guest) {
                return 0;
            }
            User::whereKey($realUser->id)->lockForUpdate()->first();
            $merged = 0;

            foreach (UserTest::where('user_id', $guest->id)->lockForUpdate()->get() as $attempt) {
                if ($attempt->isGuestTrialExpired()) {
                    continue;
                }
                if ($attempt->status === 'in_progress' && ! $attempt->assignment_id) {
                    // The real account may already be mid-attempt on this exact
                    // test — uq_user_test_active_practice allows only one such
                    // row per (test, user). Leave this one with the guest rather
                    // than lose it to a constraint violation.
                    $collides = UserTest::where('user_id', $realUser->id)
                        ->where('test_id', $attempt->test_id)
                        ->where('status', 'in_progress')
                        ->whereNull('assignment_id')
                        ->exists();

                    if ($collides) {
                        continue;
                    }
                }

                $attempt->update([
                    'user_id' => $realUser->id,
                    'guest_trial_expires_at' => null,
                    'guest_trial_absolute_expires_at' => null,
                ]);
                $merged++;
            }

            $guest->delete();

            return $merged;
        });
    }

    /**
     * The per-module question grid behind the attempt tracker.
     *
     * Built from each module's OWN question list rather than from the saved
     * answers, because an unanswered question has no `user_test_answers` row
     * until the module is submitted — driving the grid off answers would hide
     * exactly the blanks a teacher is watching for mid-module.
     *
     * Only modules the candidate has actually reached are included (ones with
     * saved work, plus whichever they are sitting now); on an adaptive test the
     * Module 2 path they were not routed to must never appear.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function questionGrid(UserTest $attempt): Collection
    {
        $attempt->loadMissing(['userAnswers', 'moduleSubmissions']);

        $moduleIds = $attempt->userAnswers->pluck('module_id')
            ->push($attempt->current_module_id)
            ->filter()
            ->unique();

        if ($moduleIds->isEmpty()) {
            return collect();
        }

        $answersByQuestion = $attempt->userAnswers->keyBy(
            fn ($answer) => $answer->module_id.':'.$answer->question_id
        );
        $modules = Module::with(['section', 'questions:id'])
            ->whereIn('id', $moduleIds)
            ->get()
            ->sortBy(fn ($module) => ($module->section->order ?? 0) * 100 + $module->module_number)
            ->values();

        /** @var list<array<string, mixed>> $grid */
        $grid = [];
        foreach ($modules as $module) {
            /** @var list<array<string, mixed>> $questions */
            $questions = [];
            foreach ($module->questions->values() as $index => $question) {
                $answer = $answersByQuestion->get($module->id.':'.$question->id);
                if (! $answer instanceof UserTestAnswer) {
                    $answer = null;
                }
                $questions[] = [
                    'number' => $index + 1,
                    'answer' => $answer,
                ];
            }

            /** @var Collection<int, array<string, mixed>> $questionsCollection */
            $questionsCollection = collect($questions);

            $grid[] = [
                'module' => $module,
                'isCurrent' => (int) $module->id === (int) $attempt->current_module_id,
                'isSubmitted' => $attempt->status === 'completed'
                    || $attempt->moduleSubmissions->contains('module_id', $module->id),
                'questions' => $questionsCollection,
            ];
        }

        /** @var Collection<int, array<string, mixed>> $result */
        $result = collect($grid);

        return $result;
    }

    public function updateStatus(ExamSession $session, string $status): ExamSession
    {
        $session->update(['status' => $status]);

        return $session;
    }

    public function resetAttempt(UserTest $attempt): void
    {
        abort_unless((bool) $attempt->exam_session_id, 422, 'Not an exam-session attempt.');
        $attempt->update(['status' => 'abandoned']);
    }

    /**
     * Teacher-triggered "end this candidate's attempt now": cascades the same
     * submit path a real student takes through every remaining module, materializing
     * unanswered questions as omitted. Delegates to AttemptEngine.
     */
    public function forceSubmit(UserTest $attempt): void
    {
        $this->engine->cascadeExpire($attempt, force: true);
    }
}
