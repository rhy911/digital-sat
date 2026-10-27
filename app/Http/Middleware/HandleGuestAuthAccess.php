<?php

namespace App\Http\Middleware;

use App\Models\UserTest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HandleGuestAuthAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->is_guest) {
            $request->session()->put('link_guest_user_id', $user->id);

            if (! $request->session()->has('link_trial_attempt_ulid')) {
                $trialUlid = UserTest::ownedCompletedPublicTrial($user->id)
                    ->latest('completed_at')
                    ->value('ulid');

                if ($trialUlid) {
                    $request->session()->put('link_trial_attempt_ulid', $trialUlid);
                }
            }
            Auth::logout();
        }

        $guestId = $request->session()->get('link_guest_user_id');
        $trialUlid = $request->session()->get('link_trial_attempt_ulid');
        if ($guestId && $trialUlid) {
            $attempt = UserTest::ownedCompletedPublicTrial((int) $guestId, (string) $trialUlid)->first();
            if (! $attempt || $attempt->isGuestTrialExpired()
                || ($attempt->guest_trial_expires_at !== null && ! $attempt->renewGuestTrialActivity())) {
                $request->session()->forget('link_trial_attempt_ulid');
                $request->session()->put('link_trial_expired_notice', 'Your practice trial expired before it could be saved. Your account can still be used, but this result is no longer available.');
            }
        }

        return $next($request);
    }
}
