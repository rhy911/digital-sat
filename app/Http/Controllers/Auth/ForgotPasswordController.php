<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Gửi link reset mật khẩu
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            // Return JSON for AJAX requests
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Password reset link sent! Check your email.',
                    'status' => $status
                ], 200);
            }
            return back()->with(['status' => __($status)]);
        } else {
            // Return JSON for AJAX requests
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => __($status),
                    'errors' => ['email' => [__($status)]]
                ], 422);
            }
            return back()->withErrors(['email' => __($status)]);
        }
    }
}
