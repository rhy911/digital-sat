<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\UserTest;
use App\Services\ExamSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Candidate join flow for standalone exam sessions. Deliberately outside the
 * `['auth', 'verified']` route groups: a fresh guest has neither, and provisioning
 * happens here rather than gating access on middleware. See ExamSessionService for
 * why a guest is a real `User` row instead of a session-only construct.
 */
class GuestExamController extends Controller
{
    public function __construct(private ExamSessionService $service) {}

    /**
     * Code-entry screen — the way in for anyone who was read a code out loud
     * rather than handed a link. Reachable signed out (a walk-in candidate) and
     * signed in (a student whose teacher is running a session), so it carries
     * no auth middleware and no role check.
     */
    public function showCodeEntry()
    {
        return view('guest.enter-code');
    }

    public function submitCode(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6', 'alpha_num'],
        ], [
            'code.size' => 'An exam code is exactly 6 characters.',
            'code.alpha_num' => 'An exam code contains only letters and numbers.',
        ]);

        $code = Str::upper($validated['code']);
        [$session, $error] = $this->service->resolveTypedCode($code);

        if (! $session) {
            return back()->withInput()->withErrors(['code' => $error]);
        }

        return redirect()->route('exam-join.show', $session->code);
    }

    public function showJoinForm(string $code)
    {
        $session = $this->service->findJoinable($code);

        return view('guest.join', [
            'session' => $session,
            'alreadySignedIn' => Auth::check() && ! (Auth::user()->is_guest ?? false),
        ]);
    }

    public function processJoin(Request $request, string $code)
    {
        $session = $this->service->findJoinable($code);

        $user = Auth::user();

        if (! $user) {
            $validated = $request->validate([
                'display_name' => 'required|string|max:100',
            ]);

            $user = $this->service->provisionGuest($validated['display_name']);
            Auth::login($user);
        }

        $guestName = $user->is_guest ? $user->name : null;
        $attempt = $this->service->startOrResumeAttempt($session, $user, $guestName);

        return redirect()->to(
            route('engine.session', ['ulid' => $attempt->currentModule->ulid]).'?attempt='.$attempt->ulid
        );
    }

    /**
     * Minimal, nav-free result page a guest lands on after finishing an exam
     * (see TestProgressionService::completionDestination). Ownership-gated
     * rather than middleware-gated: this controller sits outside
     * ['auth','verified'] on purpose, so the check happens here instead.
     */
    public function showResult(UserTest $userTest)
    {
        abort_unless(Auth::check() && (int) Auth::id() === (int) $userTest->user_id, 403);

        if ($userTest->status === 'in_progress') {
            abort_unless($userTest->current_module_id && $userTest->currentModule, 404);

            return redirect()->to(
                route('engine.session', ['ulid' => $userTest->currentModule->ulid]).'?attempt='.$userTest->ulid
            );
        }

        abort_unless($userTest->status === 'completed', 404);

        $userTest->loadMissing('test');

        return view('guest.result', ['attempt' => $userTest]);
    }

    /**
     * "Sign in/sign up to save this result", from the result page. `guest`
     * middleware on /signup and /signin would otherwise just bounce a
     * guest-authenticated visitor straight back to /home, since they already
     * pass Auth::check() — so this stashes which guest to link (session data
     * the client never controls directly, unlike a query param) and logs the
     * guest out first so those routes are reachable. RegisterController /
     * LoginController read the stash back after the real account exists and
     * call ExamSessionService::mergeGuestAccount().
     */
    public function beginAccountLink(string $destination)
    {
        abort_unless(in_array($destination, ['signup', 'signin'], true), 404);

        $user = Auth::user();
        if ($user && $user->is_guest) {
            Auth::logout();
            session(['link_guest_user_id' => $user->id]);
        }

        return redirect()->route($destination === 'signup' ? 'signup' : 'signin.form', ['role' => 'student']);
    }
}
