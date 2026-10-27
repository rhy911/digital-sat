<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Services\AttemptEngine;
use App\Services\ExamSessionService;
use App\Services\TestStructureService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class AttemptController extends Controller
{
    public function __construct(
        private AttemptEngine $engine,
        private TestStructureService $structures,
        private ExamSessionService $guests,
    ) {}

    public function attemptOptions(int|string $testId): JsonResponse
    {
        $user = Auth::user();
        $test = Test::visibleTo($user)->where('id', $testId)->where('status', 'active')->firstOrFail();
        if ($user->is_guest) {
            abort_unless(in_array($test->ulid, array_filter(config('public_trial.tests')), true), 403);
            try {
                $this->structures->validatePublicTrial($test);
            } catch (\Throwable) {
                abort(403);
            }
        }

        $latestInProgress = UserTest::where('user_id', $user->id)
            ->where('test_id', $test->id)
            ->whereNull('assignment_id')
            ->whereNull('exam_session_id')
            ->where('status', 'in_progress')
            ->where(fn ($query) => $query->whereNull('guest_trial_expires_at')->orWhere('guest_trial_expires_at', '>', now()->utc()))
            ->latest('updated_at')
            ->first();

        $firstModule = $this->engine->firstModule($test);
        $firstModuleUlid = $firstModule ? $firstModule->ulid : null;

        return response()->json([
            'has_in_progress' => ! empty($latestInProgress),
            'latest_in_progress_ulid' => $latestInProgress?->ulid,
            'latest_in_progress_current_module_ulid' => $latestInProgress?->currentModule->ulid ?? $firstModuleUlid,
            'first_module_ulid' => $firstModuleUlid,
            'can_continue' => ! empty($latestInProgress),
            'can_start_fresh' => true,
        ]);
    }

    public function startTest(Request $request, int|string $testId): JsonResponse
    {
        $user = Auth::user();
        $test = Test::visibleTo($user)->where('id', $testId)->where('status', 'active')->firstOrFail();
        $mode = $request->input('mode', 'fresh');

        $isPublicTrial = (bool) ($user->is_guest ?? false);
        if ($isPublicTrial) {
            abort_unless(in_array($test->ulid, array_filter(config('public_trial.tests')), true), 403);
            try {
                $this->structures->validatePublicTrial($test);
            } catch (\Throwable) {
                abort(403);
            }
        }

        $attempt = $this->startPracticeAttempt($user, $test, $mode, $isPublicTrial, $request->boolean('confirm_fresh'));
        if ($isPublicTrial) {
            $request->session()->put('public_trial_session', true);
        }

        return response()->json([
            'user_test_id' => $attempt->id,
            'user_test_ulid' => $attempt->ulid,
            'first_module_ulid' => $attempt->currentModule->ulid,
            'redirect_url' => route('engine.session', ['ulid' => $attempt->currentModule->ulid]).'?attempt='.$attempt->ulid,
        ]);
    }

    public function startPracticeAttempt(?User $user, Test $test, string $mode = 'continue', bool $isPublicTrial = false, bool $confirmFresh = false): UserTest
    {
        abort_unless(in_array($mode, ['continue', 'fresh'], true), 422);
        abort_if($user === null && ! $isPublicTrial, 401);
        $attemptAttributes = $test->test_type === Test::TYPE_MODULE_ONLY
            ? ['attempt_type' => 'section', 'section_type' => $test->sections()->value('type')]
            : [];
        $clientIp = (string) request()->ip();
        $packedIp = @inet_pton($clientIp);
        $normalizedIp = $packedIp === false ? 'unknown' : (inet_ntop($packedIp) ?: 'unknown');
        $ipHash = hash_hmac('sha256', $normalizedIp, (string) config('app.key'));
        $ipLock = $isPublicTrial ? Cache::lock("public_trial_ip_start:{$ipHash}", 30) : null;

        $start = function () use ($user, $test, $mode, $isPublicTrial, $confirmFresh, $attemptAttributes, $ipHash): array {
            return DB::transaction(function () use ($user, $test, $mode, $isPublicTrial, $confirmFresh, $attemptAttributes, $ipHash) {
                $owner = $user;
                if ($owner) {
                    // Lock the user record to serialize attempt starts/modifications.
                    User::whereKey($owner->id)->lockForUpdate()->first();
                }

                $active = $owner ? UserTest::where('user_id', $owner->id)->where('test_id', $test->id)
                    ->whereNull('assignment_id')->where('status', 'in_progress')->latest('updated_at')->first() : null;
                abort_if($active !== null && $active->exam_session_id !== null, 409, 'This test is already active in a teacher-led exam. Finish that session before starting practice.');

                if ($active?->is_public_trial && $active->isGuestTrialExpired()) {
                    $active->update(['status' => 'abandoned']);
                    $active = null;
                }

                if ($isPublicTrial && $active) {
                    $mode = 'continue';
                }

                abort_if($mode === 'fresh' && $active && ! $confirmFresh, 409, 'Confirm before replacing your in-progress practice test.');

                if ($mode !== 'fresh' && $active) {
                    if ($isPublicTrial && ! $active->is_public_trial) {
                        $active->forceFill(['is_public_trial' => true])->save();
                    }
                    if ($isPublicTrial) {
                        $active->renewGuestTrialActivity();
                    }
                    $this->engine->issueInitialModule($active, $test);

                    return [$active, false];
                }

                if ($isPublicTrial) {
                    $this->structures->validatePublicTrial($test);
                    $this->assertPublicTrialCreationAllowed($ipHash);
                } else {
                    $this->structures->validate($test);
                }

                if (! $owner) {
                    $owner = $this->guests->provisionGuest('Guest');
                }
                if ($mode === 'fresh' && $active) {
                    $active->update(['status' => 'abandoned']);
                }

                $now = now()->utc();
                $absolute = $isPublicTrial ? $now->copy()->addHours((int) config('public_trial.retention.absolute_hours', 24)) : null;
                $userTest = UserTest::create([
                    'user_id' => $owner->id,
                    'test_id' => $test->id,
                    'status' => 'in_progress',
                    'is_public_trial' => $isPublicTrial,
                    'guest_trial_absolute_expires_at' => $absolute,
                    'guest_trial_expires_at' => $isPublicTrial ? min($now->copy()->addMinutes((int) config('public_trial.retention.idle_minutes', 30)), $absolute) : null,
                ] + $attemptAttributes);

                $this->engine->issueInitialModule($userTest, $test);

                return [$userTest, $isPublicTrial];
            });
        };

        if ($ipLock) {
            try {
                $attempt = $ipLock->block(5, function () use ($start, $ipHash) {
                    [$attempt, $created] = $start();
                    if ($created) {
                        RateLimiter::hit("public_trial_create_10:{$ipHash}", (int) config('public_trial.creation_limits.ten_minutes.seconds', 600));
                        RateLimiter::hit("public_trial_create_60:{$ipHash}", (int) config('public_trial.creation_limits.hour.seconds', 3600));
                    }

                    return $attempt;
                });
            } catch (LockTimeoutException) {
                abort(503, 'Practice is busy right now. Please try again shortly.');
            }

            return $attempt;
        }

        [$attempt] = $start();

        return $attempt;
    }

    private function assertPublicTrialCreationAllowed(string $ipHash): void
    {
        foreach ([['public_trial_create_10', 'ten_minutes'], ['public_trial_create_60', 'hour']] as [$key, $window]) {
            $limit = config("public_trial.creation_limits.{$window}");
            $limiterKey = "{$key}:{$ipHash}";
            if (RateLimiter::tooManyAttempts($limiterKey, (int) $limit['attempts'])) {
                $retry = max(1, RateLimiter::availableIn($limiterKey));
                abort(response('Too many new guest practice attempts. Please try again in '.$retry.' seconds.', 429)
                    ->header('Retry-After', (string) $retry));
            }
        }
    }
}
