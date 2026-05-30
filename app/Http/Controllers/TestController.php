<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Services\TestManagementService;
use App\Http\Requests\StoreTestRequest;
use App\Http\Requests\UpdateTestRequest;
use Illuminate\Http\Request;

class TestController extends Controller
{
    protected TestManagementService $testManagement;

    public function __construct(TestManagementService $testManagement)
    {
        $this->testManagement = $testManagement;
    }

    public function storeTest(StoreTestRequest $request)
    {
        $validated = $request->validated();
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

    public function updateTest(UpdateTestRequest $request, $id)
    {
        $test = Test::findOrFail($id);
        $this->authorize('update', $test);

        $validated = $request->validated();
        if (isset($validated['is_public'])) {
            $validated['is_public'] = filter_var($validated['is_public'], FILTER_VALIDATE_BOOLEAN);
        }
        $test->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Test updated successfully',
            'data' => $test,
        ]);
    }

    public function deleteTest(Request $request, $id)
    {
        $test = Test::findOrFail($id);
        $this->authorize('delete', $test);

        try {
            $this->testManagement->deleteTest((int) $id, $request->boolean('delete_children'));
            return response()->json(['status' => 'success', 'message' => 'Test deleted.']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to delete test', ['exception' => $e]);
            return response()->json(['status' => 'error', 'message' => 'Failed to delete test. Please try again or contact support.'], 500);
        }
    }
}
