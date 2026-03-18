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
                return redirect()->route('login')
                    ->with('error', 'Link xác minh không hợp lệ hoặc đã hết hạn.');
            }

            if ($user->hasVerifiedEmail()) {
                // Already verified - login and go to dashboard
                Auth::login($user, true);
                return redirect()->route('dashboard')
                    ->with('info', 'Email đã được xác minh rồi.');
            }

            // Mark email as verified
            $user->markEmailAsVerified();

            // Login the user
            Auth::login($user, true);

            Log::info('Email verified successfully (Web)', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return redirect()->route('dashboard')
                ->with('success', 'Email đã được xác minh thành công! Chào mừng bạn.');
        } catch (\Exception $e) {
            Log::error('Email verification error (Web)', [
                'error' => $e->getMessage(),
                'id' => $request->route('id'),
            ]);

            return redirect()->route('login')
                ->with('error', 'Đã xảy ra lỗi khi xác minh email. Vui lòng thử lại.');
        }
    }
}
