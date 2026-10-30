<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResendVerificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => 'Your email is already verified.']);
            }
            return redirect()->route('home');
        }

        $key = 'resend_verification_' . $user->id;

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($key);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'message' => "Please wait {$seconds} seconds before trying again.",
                    'cooldown' => $seconds
                ], 429);
            }
            return back()->withErrors(['error' => "Please wait {$seconds} seconds before trying again."]);
        }

        \Illuminate\Support\Facades\RateLimiter::hit($key, 60);

        $trialUlid = $request->session()->get('link_trial_attempt_ulid');
        if ($trialUlid && ! \App\Models\UserTest::ownedCompletedPublicTrial($user->id, $trialUlid)->exists()) {
            $request->session()->forget('link_trial_attempt_ulid');
            $trialUlid = null;
        }
        $user->notify(new VerifyEmailNotification($trialUlid));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Verification email sent. Check your inbox.',
                'cooldown' => 60
            ]);
        }

        return back()->with('success', 'Verification email sent. Check your inbox.');
    }
}
