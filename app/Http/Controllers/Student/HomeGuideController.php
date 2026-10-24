<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HomeGuideController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['preference' => ['required', Rule::in(['expanded', 'hidden'])]]);
        $user = $request->user();
        $user->home_guide_preference = $data['preference'];
        $user->save();

        return response()->json(['preference' => $user->home_guide_preference]);
    }
}
