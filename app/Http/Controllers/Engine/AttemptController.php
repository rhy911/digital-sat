<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\UserTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttemptController extends Controller
{
    public function attemptOptions($testId)
    {
        $user = Auth::user();
        $test = Test::where('id', $testId)->where('status', 'active')->firstOrFail();

        $latestInProgress = UserTest::where('user_id', $user->id)
            ->where('test_id', $test->id)
            ->where('status', 'in_progress')
            ->latest('updated_at')
            ->first();

        $firstSection = $test->sections()->orderBy('order')->first();
        $firstModule = $firstSection ? $firstSection->modules()->orderBy('order')->first() : null;
        $firstModuleUlid = $firstModule ? $firstModule->ulid : null;

        return response()->json([
            'has_in_progress' => !empty($latestInProgress),
            'latest_in_progress_ulid' => $latestInProgress?->ulid,
            'latest_in_progress_current_module_ulid' => $latestInProgress?->currentModule?->ulid ?? $firstModuleUlid,
            'first_module_ulid' => $firstModuleUlid,
            'can_continue' => !empty($latestInProgress),
            'can_start_fresh' => true,
        ]);
    }

    public function startTest(Request $request, $testId)
    {
        $user = Auth::user();
        $test = Test::where('id', $testId)->where('status', 'active')->firstOrFail();

        $mode = $request->input('mode', 'fresh');

        if ($mode === 'fresh') {
            UserTest::where('user_id', $user->id)
                ->where('test_id', $test->id)
                ->where('status', 'in_progress')
                ->delete();

            $userTest = UserTest::create([
                'user_id' => $user->id,
                'test_id' => $test->id,
                'status' => 'in_progress',
            ]);
        } else {
            $userTest = UserTest::where('user_id', $user->id)
                ->where('test_id', $test->id)
                ->where('status', 'in_progress')
                ->latest('updated_at')
                ->first();

            if (!$userTest) {
                $userTest = UserTest::create([
                    'user_id' => $user->id,
                    'test_id' => $test->id,
                    'status' => 'in_progress',
                ]);
            }
        }

        $firstSection = $test->sections()->orderBy('order')->first();
        $firstModule = $firstSection ? $firstSection->modules()->orderBy('order')->first() : null;
        $firstModuleUlid = $firstModule ? $firstModule->ulid : null;

        return response()->json([
            'user_test_id' => $userTest->id,
            'user_test_ulid' => $userTest->ulid,
            'first_module_ulid' => $firstModuleUlid,
            'redirect_url' => route('engine.session', ['ulid' => $firstModuleUlid]) . '?attempt=' . $userTest->ulid,
        ]);
    }
}
