<?php

namespace App\Http\Controllers;

use App\Models\UserTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PracticeController extends Controller
{
    public function show($userTestId)
    {
        $user = Auth::user();
        $userTest = UserTest::with(['test', 'user'])
            ->where('user_id', $user->id)
            ->findOrFail($userTestId);

        $allCompletedTests = UserTest::with('test')
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->get();

        return view('tests.practice', [
            'user' => $user,
            'userTest' => $userTest, // Focused test
            'completedTests' => $allCompletedTests, // For the grid
        ]);
    }
}
