<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

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
            $message = 'Your password has been reset. Sign in with your new password.';
            if ($request->wantsJson()) {
                $request->session()->flash('success', $message);
                return response()->json([
                    'message' => $message,
                    'status' => $status,
                    'redirect' => route('signin'),
                ], 200);
            }
            return redirect()->route('signin')->with('success', $message);
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
