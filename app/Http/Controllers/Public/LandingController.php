<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LandingController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Auth::check()) {
            if ($request->hasCookie(Auth::getRecallerName())) {
                return view('auth.remembered', ['user' => Auth::user()]);
            }
            return redirect()->route('home');
        }
        return view('public.landing');
    }
}
