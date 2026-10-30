<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserTest;
use App\Services\ExamSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(private ExamSessionService $examSessions) {}

    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        try {
            if ($request->has('remember')) {
                $request->merge(['remember' => $request->boolean('remember')]);
            }

            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
                'remember' => 'boolean',
            ]);

            $credentials = $request->only('email', 'password');
            $remember = $request->boolean('remember');

            if (! Auth::attempt($credentials, $remember)) {
                throw ValidationException::withMessages([
                    'email' => ['These sign-in details do not match an account.'],
                ]);
            }

            /** @var User $user */
            $user = Auth::user();

            $request->session()->regenerate();

            if ($guestId = $request->session()->pull('link_guest_user_id')) {
                $this->examSessions->mergeGuestAccount((int) $guestId, $user);
            }

            $trialUlid = $request->session()->get('link_trial_attempt_ulid');
            if ($trialUlid && ! UserTest::ownedCompletedPublicTrial($user->id, $trialUlid)->exists()) {
                $request->session()->forget('link_trial_attempt_ulid');
                $trialUlid = null;
                $request->session()->put('link_trial_expired_notice', 'Your practice trial could not be saved. Your account is signed in, but this result is no longer available.');
            }
            $trialNotice = $request->session()->pull('link_trial_expired_notice');
            if ($trialUlid && $user->hasVerifiedEmail()) {
                if ($user->role !== 'teacher' || $user->isApprovedTeacher()) {
                    $request->session()->put('url.intended', route('student.scores.show', ['userTest' => $trialUlid]));
                }
                $request->session()->forget('link_trial_attempt_ulid');
            }

            // Check if email is verified
            if (! $user->hasVerifiedEmail()) {
                $request->session()->flash(
                    'warning',
                    trim('Verify your email address to continue. '.($trialNotice ?? ''))
                );
                // Return JSON for AJAX requests or redirect for form submissions
                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => trim('Verify your email address to continue. '.($trialNotice ?? '')),
                        'redirect' => route('verify.email.notice'),
                    ], 403);
                }

                return redirect()->route('verify.email.notice')
                    ->with('warning', trim('Verify your email address to continue. '.($trialNotice ?? '')));
            }

            $defaultTarget = route('dashboard');

            // Return JSON response for AJAX or redirect for web
            if ($request->wantsJson()) {
                $target = $user->role === 'teacher' && ! $user->isApprovedTeacher()
                    ? $defaultTarget
                    : $request->session()->pull('url.intended', $defaultTarget);

                return response()->json([
                    'message' => trim('Sign-in successful.'.($trialNotice ? ' '.$trialNotice : '')),
                    'user' => $user,
                    'email_verified' => $user->hasVerifiedEmail(),
                    'redirect' => $target,
                ], 200);
            }

            $response = $user->role === 'teacher' && ! $user->isApprovedTeacher()
                ? redirect()->route('dashboard')
                : redirect()->intended($defaultTarget);

            return $trialNotice ? $response->with('warning', $trialNotice) : $response;
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                $message = $e->validator->errors()->first();
            } else {
                $message = 'We could not sign you in. Please try again.';
                Log::error('Login error (Web):', [
                    'error' => $e->getMessage(),
                    'email' => $request->input('email'),
                ]);
            }

            // Return JSON for AJAX requests
            if ($request->wantsJson()) {
                $response = ['message' => $message];
                if ($e instanceof ValidationException) {
                    $response['errors'] = $e->errors();
                }
                return response()->json($response, 422);
            }

            return redirect()->back()
                ->withInput($request->except('password'))
                ->withErrors($e instanceof ValidationException ? $e->errors() : ['error' => $message]);
        }
    }
}
