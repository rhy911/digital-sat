<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ApplicationStatusController extends Controller
{
    public function __invoke(Request $request): View { return view('teacher.application-status', ['user' => $request->user()]); }
}
