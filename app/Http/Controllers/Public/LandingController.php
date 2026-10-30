<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LandingController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (Auth::check() && ! Auth::user()->is_guest) {
            return redirect()->route('dashboard');
        }

        return view('public.landing');
    }
}
