<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTestRequest;
use App\Http\Requests\Admin\UpdateTestRequest;
use App\Models\Test;
use App\Services\TestManagementService;
use App\Services\TestStructureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TestController extends Controller
{
    protected TestManagementService $testManagement;

    public function __construct(TestManagementService $testManagement, private TestStructureService $structures)
    {
        $this->testManagement = $testManagement;
    }

    public function store(StoreTestRequest $request)
    {
        $validated = $request->validated();
        if (($validated['status'] ?? 'draft') === 'active') {
            throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Create the test as a draft, add a valid structure, then activate it.']);
        }
        $validated['total_duration_minutes'] = 0;
        $validated['created_by'] = auth()->id();
        $validated['is_public'] = $request->boolean('is_public', false);
        $test = Test::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Test created successfully',
            'data' => $test,
        ], 201);
    }

    public function update(UpdateTestRequest $request, $id)
    {
        $test = Test::findOrFail($id);
        $this->authorize('update', $test);
        if ($test->isContentLocked() && collect(array_keys($request->validated()))->intersect(['test_type', 'total_duration_minutes', 'break_duration_minutes', 'status'])->isNotEmpty()) {
            app(\App\Services\TestContentLockService::class)->ensureUnlocked($test);
        }

        $validated = $request->validated();
        if (isset($validated['test_type']) && $validated['test_type'] !== $test->test_type && $test->userTests()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['test_type' => 'Test type cannot change after student attempts exist. Clone the test into a new draft.']);
        }
        if (isset($validated['is_public'])) {
            $validated['is_public'] = filter_var($validated['is_public'], FILTER_VALIDATE_BOOLEAN);
        }
        DB::transaction(function () use ($test, $validated) {
            $test->update($validated);
            if ($test->status === 'active') {
                $this->structures->validateForPublication($test->fresh());
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Test updated successfully',
            'data' => $test,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $test = Test::findOrFail($id);
        $this->authorize('delete', $test);
        app(\App\Services\TestContentLockService::class)->ensureUnlocked($test);

        try {
            $this->testManagement->deleteTest((int) $id, $request->boolean('delete_children'));

            return response()->json(['status' => 'success', 'message' => 'Test deleted.']);
        } catch (\Exception $e) {
            Log::error('Failed to delete test', ['exception' => $e]);

            return response()->json(['status' => 'error', 'message' => 'Failed to delete test. Please try again or contact support.'], 500);
        }
    }
}
