<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginWebController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
                'remember' => 'boolean'
            ]);

            $credentials = $request->only('email', 'password');
            $remember = $request->boolean('remember');

            if (!Auth::attempt($credentials, $remember)) {
                throw ValidationException::withMessages([
                    'email' => ['Thông tin đăng nhập không chính xác.'],
                ]);
            }

            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Check if email is verified
            if (!$user->hasVerifiedEmail()) {
                // Return JSON for AJAX requests or redirect for form submissions
                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'Email của bạn chưa được xác minh.',
                        'redirect' => route('verify.email.notice')
                    ], 403);
                }

                return redirect()->route('verify.email.notice')
                    ->with('warning', 'Email của bạn chưa được xác minh.');
            }

            // Return JSON response with token for successful login
            /** @var \App\Models\User $user */
            return response()->json([
                'message' => 'Đăng nhập thành công.',
                'user' => $user,
                'email_verified' => $user->hasVerifiedEmail(),
                'token' => $user->createToken('api-token')->plainTextToken,
            ], 200);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if ($e instanceof ValidationException) {
                $message = $e->validator->errors()->first();
            }

            Log::error('Login error (Web):', [
                'error' => $message,
                'email' => $request->email ?? null,
            ]);

            // Return JSON for AJAX requests
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $message
                ], 422);
            }

            return redirect()->back()
                ->withInput($request->except('password'))
                ->withErrors(['error' => $message]);
        }
    }
}
