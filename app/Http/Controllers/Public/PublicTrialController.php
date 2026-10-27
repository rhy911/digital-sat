<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Engine\AttemptController;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Services\ScoreReportService;
use App\Services\TestStructureService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicTrialController extends Controller
{
    public function screenSize(): View
    {
        return view('public.trial-screen-size');
    }

    public function expired(Request $request): View
    {
        return view('public.trial-expired', ['attemptId' => $request->integer('attempt')]);
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        if (! $user) {
            $sessionId = (string) $request->session()->getId();
            $guestId = $request->session()->get('link_guest_user_id')
                ?? Cache::get("public_trial_guest:{$sessionId}");
            if ($guestId) {
                $candidate = User::find($guestId);
                if ($candidate && $candidate->is_guest) {
                    $user = $candidate;
                    Auth::login($user);
                }
            }
        }

        $attempts = $user
            ? UserTest::where('user_id', $user->id)->where('is_public_trial', true)
                ->where(fn ($query) => $query->whereNull('guest_trial_expires_at')->orWhere('guest_trial_expires_at', '>', now()->utc()))
                ->with(['test.sections.modules', 'currentModule'])->latest('updated_at')->limit(6)->get()
                ->filter(fn (UserTest $a) => ! $a->isGuestTrialExpired())
            : collect();

        return view('public.try', ['options' => $this->options(), 'attempts' => $attempts]);
    }

    public function start(Request $request, AttemptController $attempts, TestStructureService $structures): RedirectResponse
    {
        $data = $request->validate([
            'experience' => ['required', 'in:module,full'],
            'section' => ['nullable', 'in:reading_writing,math'],
            'mode' => ['nullable', 'in:continue,fresh'],
            'confirm_fresh' => ['nullable', 'boolean'],
        ]);
        $section = $data['section'] ?? null;
        if ($data['experience'] === 'module' && ! $section) {
            throw ValidationException::withMessages(['section' => 'Choose Reading and Writing or Math.']);
        }

        $key = $data['experience'] === 'full' ? 'full' : $section;
        $ulid = config("public_trial.tests.{$key}");
        $test = $ulid ? Test::where('ulid', $ulid)->where('status', 'active')->where('is_public', true)->first() : null;
        if (! $test) {
            return back()->withInput()->with('error', 'This practice option is temporarily unavailable. Explore the interface demo instead.');
        }
        try {
            $structures->validatePublicTrial($test, $key);
        } catch (\Throwable) {
            return back()->withInput()->with('error', 'This practice option is temporarily unavailable. Explore the interface demo instead.');
        }

        $sessionId = (string) $request->session()->getId();
        $lock = Cache::lock("public_trial_start:{$sessionId}", 30);

        try {
            return $lock->block(10, function () use ($request, $sessionId, $test, $data, $attempts) {
                $user = Auth::user();
                if ($user && ! $user->is_guest && ! $user->hasVerifiedEmail()) {
                    return redirect()->route('verify.email.notice')->with('warning', 'Verify your email to start practice.');
                }

                if (! $user) {
                    $cachedGuestId = Cache::get("public_trial_guest:{$sessionId}");
                    if ($cachedGuestId) {
                        $candidate = User::find($cachedGuestId);
                        if ($candidate && $candidate->is_guest) {
                            $user = $candidate;
                        }
                    }

                }

                $mode = $data['mode'] ?? 'continue';
                if ($user) {
                    $active = UserTest::where('user_id', $user->id)->where('test_id', $test->id)
                        ->whereNull('assignment_id')->where('status', 'in_progress')->latest('updated_at')->first();
                    if ($active && ! $active->isGuestTrialExpired()) {
                        $mode = 'continue';
                    }
                }

                $attempt = $attempts->startPracticeAttempt($user, $test, $mode, ! $user || $user->is_guest, false);
                if (! $user) {
                    $user = $attempt->user;
                    Auth::login($user);
                    Cache::put("public_trial_guest:{$sessionId}", $user->id, 120);
                } elseif ($user->is_guest && ! Auth::check()) {
                    Auth::login($user);
                }
                if ($user->is_guest) {
                    $request->session()->put('public_trial_session', true);
                }

                return redirect()->to(route('engine.session', ['ulid' => $attempt->currentModule->ulid]).'?attempt='.$attempt->ulid);
            });
        } catch (LockTimeoutException) {
            return back()->withInput()->with('error', 'Your practice session is taking longer than expected to prepare. Please try again.');
        }
    }

    public function result(Request $request, UserTest $userTest, ScoreReportService $reports): View|RedirectResponse|JsonResponse
    {
        $this->authorize('view', $userTest);
        abort_unless($userTest->is_public_trial, 404);
        if ($userTest->isGuestTrialExpired()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'trial_expired', 'message' => 'This guest practice trial has expired.'], 410);
            }

            return redirect()->route('public-trial.expired', ['attempt' => $userTest->id]);
        }
        $userTest->renewGuestTrialActivity();
        if ($userTest->status !== 'completed') {
            if ($userTest->status === 'in_progress' && $userTest->currentModule) {
                return redirect()->to(route('engine.session', ['ulid' => $userTest->currentModule->ulid]).'?attempt='.$userTest->ulid);
            }
            abort(404);
        }
        if (! Auth::user()->is_guest) {
            return redirect()->route('student.scores.show', $userTest);
        }

        return view('public.trial-result', $reports->buildPublicTrialSummary($userTest));
    }

    public function linkAccount(UserTest $userTest, string $destination): RedirectResponse
    {
        abort_unless(in_array($destination, ['signup', 'signin'], true), 404);
        abort_unless(Auth::check() && Auth::user()->is_guest && (int) Auth::id() === (int) $userTest->user_id
            && $userTest->is_public_trial && $userTest->status === 'completed', 403);
        if ($userTest->isGuestTrialExpired()) {
            return redirect()->route('public-trial.expired', ['attempt' => $userTest->id]);
        }
        if ($userTest->guest_trial_expires_at !== null && ! $userTest->renewGuestTrialActivity()) {
            return redirect()->route('public-trial.expired', ['attempt' => $userTest->id]);
        }

        session(['link_guest_user_id' => Auth::id(), 'link_trial_attempt_ulid' => $userTest->ulid]);
        Auth::logout();
        session()->regenerate();

        return redirect()->route($destination === 'signup' ? 'signup' : 'signin.form', ['role' => 'student']);
    }

    /** @return array<string, array{available: bool}> */
    private function options(): array
    {
        $options = [];
        $structures = app(TestStructureService::class);
        foreach (['reading_writing', 'math', 'full'] as $key) {
            $ulid = config("public_trial.tests.{$key}");
            $test = $ulid ? Test::where('ulid', $ulid)->where('status', 'active')->where('is_public', true)->first() : null;
            if ($test) {
                try {
                    $structures->validatePublicTrial($test, $key);
                } catch (\Throwable) {
                    $test = null;
                }
            }
            $options[$key] = ['available' => (bool) $test];
        }

        return $options;
    }
}
