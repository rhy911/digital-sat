<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\UserTest;
use App\Models\Test;
use Illuminate\Support\Facades\Auth;

class PracticeController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $tests = Test::visibleTo($user)
            ->with([
                'sections.modules' => fn ($query) => $query->visibleTo($user),
                'userTests' => fn ($query) => $query->where('user_id', $user->id)->with(['test.sections.modules', 'currentModule.section'])->orderBy('updated_at', 'desc'),
            ])
            ->where('status', 'active')
            ->where('title', '!=', 'Test Preview')
            ->limit(100)
            ->get();

        return view('student.practice.index', compact('tests', 'user'));
    }

    public function show(UserTest $userTest)
    {
        $user = Auth::user();
        $this->authorize('view', $userTest);
        $userTest->load(['test', 'user']);

        $allCompletedTests = UserTest::with('test')
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->get();

        return view('student.practice.show', [
            'user' => $user,
            'userTest' => $userTest,
            'completedTests' => $allCompletedTests,
        ]);
    }

    public function preview()
    {
        return view('student.practice.preview', [
            'user' => Auth::user(),
        ]);
    }

    public function destroy(UserTest $userTest)
    {
        $this->authorize('delete', $userTest);

        $userTest->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Attempt deleted successfully.']);
        }

        return redirect()->route('home')->with('success', 'Practice attempt deleted.');
    }
}
