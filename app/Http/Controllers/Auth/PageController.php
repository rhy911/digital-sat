<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function showSignin()
    {
        return view('auth.role-select');
    }

    public function showSigninForm()
    {
        return view('auth.signin');
    }

    public function showSignup()
    {
        return view('auth.signup');
    }

    public function showForgot()
    {
        return view('auth.forgot');
    }

    public function showEmailVerifyNotice()
    {
        return view('auth.email-verify');
    }

    public function showResetPassword(Request $request, $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->email]);
    }
}
