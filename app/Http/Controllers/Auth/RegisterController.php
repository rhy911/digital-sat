<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    public function __invoke(Request $request)
    {
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

        // Gửi email xác minh
        $user->notify(new VerifyEmailNotification());

        Log::info('User register API info:', $user->toArray());

        Auth::login($user);

        return response()->json([
            'message' => 'Đăng ký thành công. Vui lòng xác minh email của bạn.',
            'user' => $user,
            'token' => $user->createToken('api-token')->plainTextToken,
        ]);
    }
}
