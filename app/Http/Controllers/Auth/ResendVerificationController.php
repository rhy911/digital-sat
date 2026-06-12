<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\Request;

class ResendVerificationController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email đã được xác minh rồi.']);
        }

        $key = 'resend_verification_' . $user->id;

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Vui lòng đợi {$seconds} giây trước khi gửi lại.",
                'cooldown' => $seconds
            ], 429);
        }

        \Illuminate\Support\Facades\RateLimiter::hit($key, 60);

        $user->notify(new VerifyEmailNotification());

        return response()->json([
            'message' => 'Email xác minh đã được gửi lại.',
            'cooldown' => 60
        ]);
    }
}
