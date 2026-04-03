<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;

class ResetPasswordController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        // Thực hiện đặt lại mật khẩu
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Password reset successfully!',
                    'status' => $status,
                    'redirect' => route('signin')
                ], 200);
            }
            return redirect()->route('signin')->with('status', __($status));
        } else {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => __($status),
                    'errors' => ['email' => [__($status)]]
                ], 422);
            }
            return back()->withErrors(['email' => [__($status)]]);
        }
    }
}
