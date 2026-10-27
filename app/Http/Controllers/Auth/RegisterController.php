<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\ExamSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    public function __construct(private ExamSessionService $examSessions) {}

    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        try {
            $request->validate([
                'username' => 'required|string|max:255|unique:users',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'nullable|string|in:student,teacher',
            ]);

            $role = $request->input('role', 'student');
            $user = User::create([
                'username' => $request->username,
                'name' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'teacher_approval_status' => $role === 'teacher' ? 'pending' : null,
            ]);
            $user->role = $role;
            $user->save();

            Log::info('User created via Web', ['id' => $user->id]);

            Auth::login($user);

            $guestId = $request->session()->pull('link_guest_user_id');
            $trialUlid = $request->session()->get('link_trial_attempt_ulid');
            if ($guestId) {
                $this->examSessions->mergeGuestAccount((int) $guestId, $user);
            }

            if ($trialUlid && ! \App\Models\UserTest::ownedCompletedPublicTrial($user->id, $trialUlid)->exists()) {
                $request->session()->forget('link_trial_attempt_ulid');
                $trialUlid = null;
                $request->session()->put('link_trial_expired_notice', 'Your practice trial could not be saved. Your account is ready, but this result is no longer available.');
            }
            $trialNotice = $request->session()->pull('link_trial_expired_notice');

            $mailSent = true;
            try {
                $user->notify(new VerifyEmailNotification($trialUlid));
            } catch (\Throwable $mailError) {
                $mailSent = false;
                Log::warning('Verification email delivery failed after registration.', ['user_id' => $user->id, 'error' => $mailError->getMessage()]);
            }

            $noticeMessage = $mailSent
                ? 'Your account is ready. Check your email to verify it. You can resend the message if it does not arrive.'
                : ($trialUlid
                    ? 'Your account has been created and your practice test saved, but we could not deliver your verification email. Please try resending it.'
                    : 'Your account has been created, but we could not deliver your verification email. Please try resending it.');
            if ($trialNotice) {
                $noticeMessage .= ' '.$trialNotice;
            }

            // Return JSON for AJAX requests
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $noticeMessage,
                    'user' => $user,
                    'redirect' => route('verify.email.notice')
                ], 201);
            }

            return redirect()->route('verify.email.notice')
                ->with('warning', $noticeMessage);
        } catch (\Exception $e) {
            $isValidationError = $e instanceof \Illuminate\Validation\ValidationException;
            $message = $isValidationError ? $e->getMessage() : 'We could not create your account. Please try again.';

            if (!$isValidationError) {
                Log::error('Register error details (Web):', [
                    'error' => $e->getMessage(),
                    'email' => $request->input('email'),
                ]);
            }

            // Return JSON for AJAX requests
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $message
                ], 422);
            }

            return redirect()->back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['error' => $message]);
        }
    }
}
