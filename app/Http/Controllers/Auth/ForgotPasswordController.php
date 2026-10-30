<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            // Return JSON for AJAX requests
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'If that email address is registered, a password reset link will be sent.',
                    'status' => $status
                ], 200);
            }
            return back()->with(['success' => 'If that email address is registered, a password reset link will be sent.']);
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
