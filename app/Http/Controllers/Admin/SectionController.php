<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Test;
use App\Services\TestManagementService;
use App\Http\Requests\Admin\StoreSectionRequest;
use App\Http\Requests\Admin\UpdateSectionRequest;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SectionController extends Controller
{
    protected TestManagementService $testManagement;

    public function __construct(TestManagementService $testManagement)
    {
        $this->testManagement = $testManagement;
    }

    public function store(StoreSectionRequest $request)
    {
        $validated = $request->validated();

        $test = Test::findOrFail($validated['test_id']);
        $this->authorize('update', $test);
        app(\App\Services\TestContentLockService::class)->ensureUnlocked($test);

        if (Section::where('test_id', $validated['test_id'])->where('type', $validated['type'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This test already has a section for that type.',
            ], 422);
        }

        $validated['order'] = $validated['type'] === Section::TYPE_RW ? 1 : 2;
        if (empty($validated['name'])) {
            $validated['name'] = $validated['type'] === Section::TYPE_RW ? 'Reading and Writing' : 'Math';
        }
        $validated['created_by'] = auth()->id();
        $validated['is_public'] = $request->boolean('is_public', false);
        $section = Section::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Section created successfully',
            'data' => $section,
        ], 201);
    }

    public function update(UpdateSectionRequest $request, $id)
    {
        $section = Section::findOrFail($id);
        $this->authorize('update', $section);
        app(\App\Services\TestContentLockService::class)->ensureUnlocked($section->test);

        $validated = $request->validated();
        if (isset($validated['is_public'])) {
            $validated['is_public'] = filter_var($validated['is_public'], FILTER_VALIDATE_BOOLEAN);
        }
        $section->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Section updated successfully',
            'data' => $section,
        ]);
    }


    public function destroy(Request $request, $id)
    {
        $section = Section::findOrFail($id);
        $this->authorize('delete', $section);
        app(\App\Services\TestContentLockService::class)->ensureUnlocked($section->test);

        try {
            $this->testManagement->deleteSection((int) $id, $request->boolean('delete_children'));
            return response()->json(['status' => 'success', 'message' => 'Section deleted.']);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to delete section', ['exception' => $e]);
            return response()->json(['status' => 'error', 'message' => 'Failed to delete section. Please try again or contact support.'], 500);
        }
    }
}
