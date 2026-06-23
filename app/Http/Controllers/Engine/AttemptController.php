<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\UserTest;
use App\Services\AttemptProgressionService;
use App\Services\TestStructureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttemptController extends Controller
{
    public function __construct(
        private AttemptProgressionService $progression,
        private TestStructureService $structures,
    ) {}

    public function attemptOptions($testId)
    {
        $user = Auth::user();
        $test = Test::visibleTo($user)->where('id', $testId)->where('status', 'active')->firstOrFail();

        $latestInProgress = UserTest::where('user_id', $user->id)
            ->where('test_id', $test->id)
            ->whereNull('assignment_id')
            ->where('status', 'in_progress')
            ->latest('updated_at')
            ->first();

        $firstModule = $this->progression->firstModule($test);
        $firstModuleUlid = $firstModule ? $firstModule->ulid : null;

        return response()->json([
            'has_in_progress' => ! empty($latestInProgress),
            'latest_in_progress_ulid' => $latestInProgress?->ulid,
            'latest_in_progress_current_module_ulid' => $latestInProgress?->currentModule?->ulid ?? $firstModuleUlid,
            'first_module_ulid' => $firstModuleUlid,
            'can_continue' => ! empty($latestInProgress),
            'can_start_fresh' => true,
        ]);
    }

    public function startTest(Request $request, $testId)
    {
        $user = Auth::user();
        $test = Test::visibleTo($user)->where('id', $testId)->where('status', 'active')->firstOrFail();

        $mode = $request->input('mode', 'fresh');

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user, $test, $mode) {
            // Lock the user record to serialize attempt starts/modifications
            \App\Models\User::where('id', $user->id)->lockForUpdate()->first();

            if ($mode !== 'fresh') {
                $existing = UserTest::where('user_id', $user->id)
                    ->where('test_id', $test->id)->whereNull('assignment_id')
                    ->where('status', 'in_progress')->latest('updated_at')->first();
                if ($existing) {
                    $this->progression->issueInitialModule($existing, $test);

                    return response()->json([
                        'user_test_id' => $existing->id,
                        'user_test_ulid' => $existing->ulid,
                        'first_module_ulid' => $existing->currentModule->ulid,
                        'redirect_url' => route('engine.session', ['ulid' => $existing->currentModule->ulid]).'?attempt='.$existing->ulid,
                    ]);
                }
            }

            $this->structures->validateForPublication($test);

            if ($mode === 'fresh') {
                UserTest::where('user_id', $user->id)
                    ->where('test_id', $test->id)
                    ->whereNull('assignment_id')
                    ->where('status', 'in_progress')
                    ->update(['status' => 'abandoned']);

                $userTest = UserTest::create([
                    'user_id' => $user->id,
                    'test_id' => $test->id,
                    'status' => 'in_progress',
                ]);
            } else {
                $userTest = UserTest::where('user_id', $user->id)
                    ->where('test_id', $test->id)
                    ->whereNull('assignment_id')
                    ->where('status', 'in_progress')
                    ->latest('updated_at')
                    ->first();

                if (! $userTest) {
                    $userTest = UserTest::create([
                        'user_id' => $user->id,
                        'test_id' => $test->id,
                        'status' => 'in_progress',
                    ]);
                }
            }

            $firstModule = $this->progression->issueInitialModule($userTest, $test);
            $firstModuleUlid = $firstModule ? $firstModule->ulid : null;

            return response()->json([
                'user_test_id' => $userTest->id,
                'user_test_ulid' => $userTest->ulid,
                'first_module_ulid' => $firstModuleUlid,
                'redirect_url' => route('engine.session', ['ulid' => $userTest->currentModule->ulid]).'?attempt='.$userTest->ulid,
            ]);
        });
    }
}
