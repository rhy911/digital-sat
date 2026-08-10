<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\UserTest;
use App\Models\Test;
use App\Services\AttemptProgressionService;
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
        $this->authorize('view', $userTest);

        if ($userTest->status === 'in_progress') {
            // The engine only accepts the module the attempt currently holds
            // (AttemptProgressionService::assertIssued), so an attempt without
            // one cannot be resumed — send the student back to the library
            // instead of a half-empty score report.
            $moduleUlid = $userTest->currentModule?->ulid;

            return $moduleUlid
                ? redirect()->route('engine.session', ['ulid' => $moduleUlid, 'attempt' => $userTest->ulid])
                : redirect()->route('home.practice')->with('error', 'That practice attempt cannot be resumed.');
        }

        return redirect()->route('student.scores.show', $userTest);
    }

    public function resume(UserTest $userTest, AttemptProgressionService $progression)
    {
        $this->authorize('view', $userTest);
        abort_unless($userTest->status === 'in_progress', 409, 'This attempt is no longer active.');

        // Idempotent: returns the module already issued, or issues the first one
        // for an attempt that never got past creation.
        $module = $progression->issueInitialModule($userTest, $userTest->test);

        return redirect()->route('engine.session', ['ulid' => $module->ulid, 'attempt' => $userTest->ulid]);
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
