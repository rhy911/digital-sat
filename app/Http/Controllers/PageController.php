<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PageController extends Controller
{
    public function landing()
    {
        if (Auth::check()) {
            if (request()->hasCookie(Auth::getRecallerName())) {
                return view('auth.remembered', ['user' => Auth::user()]);
            }
            return redirect()->route('home');
        }
        return view('landing');
    }

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
