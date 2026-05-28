<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class VerifyEmailWebController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            $user = User::findOrFail($request->route('id'));

            if (!hash_equals(
                (string) $request->route('hash'),
                sha1($user->getEmailForVerification())
            )) {
                // Invalid hash - redirect to login with error
                return redirect()->route('signin')
                    ->with('error', 'Link xác minh không hợp lệ hoặc đã hết hạn.');
            }

            if ($user->hasVerifiedEmail()) {
                // Already verified - login and show success screen
                Auth::login($user, true);
                return view('auth.email-verified');
            }

            // Mark email as verified
            $user->markEmailAsVerified();

            // Login the user
            Auth::login($user, true);

            Log::info('Email verified successfully (Web)', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return view('auth.email-verified');
        } catch (\Exception $e) {
            Log::error('Email verification error (Web)', [
                'error' => $e->getMessage(),
                'id' => $request->route('id'),
            ]);

            return redirect()->route('signin')
                ->with('error', 'Đã xảy ra lỗi khi xác minh email. Vui lòng thử lại.');
        }
    }
}
