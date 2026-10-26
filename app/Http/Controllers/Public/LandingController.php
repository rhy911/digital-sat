<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LandingController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
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
