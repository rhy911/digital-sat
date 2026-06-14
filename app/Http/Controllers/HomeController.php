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
        return view('home', $this->homeData($request));
    }

    public function progress(Request $request)
    {
        return view('home-progress', $this->homeData($request));
    }

    private function homeData(Request $request): array
    {
        $user = $request->user();
        $completedTests = $user->userTests()
            ->whereHas('test', fn($q) => $q->where('title', '!=', 'Test Preview'))
            ->with('test')
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->get();

        $inProgressTests = $user->userTests()
            ->whereHas('test', fn($q) => $q->where('title', '!=', 'Test Preview'))
            ->with('test')
            ->where('status', 'in_progress')
            ->orderBy('updated_at', 'desc')
            ->get();

        return [
            'user' => $user,
            'completedTests' => $completedTests,
            'inProgressTests' => $inProgressTests,
        ];
    }
}
