<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function showSignin(): View
    {
        return view('auth.role-select');
    }

    public function showSigninForm(): View
    {
        return view('auth.signin');
    }

    public function showSignup(): View
    {
        return view('auth.signup');
    }

    public function showForgot(): View
    {
        return view('auth.forgot');
    }

    public function showEmailVerifyNotice(): View
    {
        return view('auth.email-verify');
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->email]);
    }
}
