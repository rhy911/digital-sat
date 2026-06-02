<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $completedTests = $user->userTests()
            ->with('test')
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->get();

        $inProgressTests = $user->userTests()
            ->with('test')
            ->where('status', 'in_progress')
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('home', [
            'user' => $user,
            'completedTests' => $completedTests,
            'inProgressTests' => $inProgressTests,
        ]);
    }
}
