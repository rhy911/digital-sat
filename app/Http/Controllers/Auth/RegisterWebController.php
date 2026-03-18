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
        Log::info('RegisterWeb called', $request->all());
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            Log::info('User created via Web', $user->toArray());

            // Gửi email xác minh
            $user->notify(new VerifyEmailNotification());

            Auth::login($user);

            // Return JSON for AJAX requests
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Đăng ký thành công! Vui lòng xác minh email để tiếp tục.',
                    'user' => $user,
                    'token' => $user->createToken('api-token')->plainTextToken,
                    'redirect' => route('verify.email.notice')
                ], 201);
            }

            return redirect()->route('verify.email.notice')
                ->with('warning', 'Đăng ký thành công! Vui lòng xác minh email để tiếp tục.');
        } catch (\Exception $e) {
            $message = $e->getMessage();

            Log::error('Register error details (Web):', [
                'error' => $message,
                'request_data' => $request->all(),
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
