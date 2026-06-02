<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RegisterWebController extends Controller
{
    public function __invoke(Request $request)
    {
        Log::info('RegisterWeb called', ['email' => $request->email]);
        try {
            $request->validate([
                'username' => 'required|string|max:255|unique:users',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'nullable|string|in:student,teacher',
            ]);

            if ($request->input('role') === 'teacher') {
                $message = 'Teacher signup is temporarily unavailable. Please sign in with an existing teacher account or contact an administrator.';

                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => $message,
                        'errors' => [
                            'role' => [$message],
                        ],
                    ], 422);
                }

                return redirect()->back()
                    ->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors(['role' => $message]);
            }

            $user = User::create([
                'username' => $request->username,
                'name' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
            
            $user->role = $request->input('role', 'student');
            $user->save();

            Log::info('User created via Web', ['id' => $user->id, 'email' => $user->email]);

            // Gửi email xác minh
            $user->notify(new VerifyEmailNotification());

            Auth::login($user);

            // Return JSON for AJAX requests
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Đăng ký thành công! Vui lòng xác minh email để tiếp tục.',
                    'user' => $user,
                    'redirect' => route('verify.email.notice')
                ], 201);
            }

            return redirect()->route('verify.email.notice')
                ->with('warning', 'Đăng ký thành công! Vui lòng xác minh email để tiếp tục.');
        } catch (\Exception $e) {
            $message = $e->getMessage();

            Log::error('Register error details (Web):', [
                'error' => $message,
                'email' => $request->email,
            ]);

            // Return JSON for AJAX requests
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $message
                ], 422);
            }

            return redirect()->back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['error' => $message]);
        }
    }
}
