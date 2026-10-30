<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        try {
            $user = User::findOrFail($request->route('id'));

            if (!$request->hasValidSignature()) {
                return redirect()->route('signin')
                    ->with('error', 'This verification link is invalid or has expired.');
            }

            if (!hash_equals(
                (string) $request->route('hash'),
                sha1($user->getEmailForVerification())
            )) {
                return redirect()->route('signin')
                    ->with('error', 'This verification link is invalid or has expired.');
            }

            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
                Log::info('Email verified successfully (Web)', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }

            Auth::login($user, true);
            $request->session()->forget('link_trial_attempt_ulid');
            $request->session()->forget('link_guest_user_id');

            $trialUlid = $request->query('trial');
            if ($trialUlid !== null && $trialUlid !== '') {
                $hasEligibleTrial = \App\Models\UserTest::ownedCompletedPublicTrial($user->id, $trialUlid)->exists();
                if (!$hasEligibleTrial) {
                    return redirect()->route('dashboard')
                        ->with('warning', 'The requested trial report is no longer available. Welcome to your DFET SAT dashboard.');
                }

                return view('auth.email-verified', [
                    'continueUrl' => $user->role === 'teacher' && ! $user->isApprovedTeacher()
                        ? route('dashboard')
                        : route('student.scores.show', ['userTest' => $trialUlid]),
                ]);
            }

            return view('auth.email-verified', [
                'continueUrl' => route('dashboard'),
            ]);
        } catch (\Exception $e) {
            Log::error('Email verification error (Web)', [
                'error' => $e->getMessage(),
                'id' => $request->route('id'),
            ]);

            return redirect()->route('signin')
                ->with('error', 'We could not verify this email. Please try again.');
        }
    }
}
