<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use App\Services\TestContentLockService;
use App\Services\TestManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TestStructureController extends Controller
{
    protected TestManagementService $testManagement;

    public function __construct(TestManagementService $testManagement)
    {
        $this->testManagement = $testManagement;
    }

    public function generateFullSat(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'test_type' => 'sometimes|string|in:full_length,adaptive_full_length,short_test,module_only',
        ]);

        $testType = $validated['test_type'] ?? 'full_length';

        try {
            $test = $this->testManagement->generateFullSatStructure($validated['title'], $testType, auth()->id());

            return response()->json([
                'status' => 'success',
                'message' => 'SAT Structure created successfully.',
                'data' => $test,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to generate structure', ['exception' => $e]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate structure. Please try again or contact support.',
            ], 500);
        }
    }

    public function generateConfigured(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'test_type' => 'required|string|in:full_length,adaptive_full_length,short_test,module_only,section_only,custom_test',
            'status' => 'sometimes|string|in:draft,active,archived',
            'break_duration_minutes' => 'sometimes|integer|min:0|max:120',
            'populate_from_pool' => 'sometimes|boolean',
            'modules' => 'required|array|min:1|max:20',
            'modules.*.section_type' => 'required|string|in:reading_writing,math',
            'modules.*.module_number' => 'required|integer|min:1|max:10',
            'modules.*.difficulty_level' => 'required|string|in:standard,easy,hard',
            'modules.*.duration_minutes' => 'required|integer|min:1|max:240',
            'modules.*.total_questions' => 'required|integer|min:1|max:100',
        ]);

        try {
            $test = $this->testManagement->createConfiguredTestFromBlueprint($validated, auth()->user(), auth()->id());

            return response()->json([
                'status' => 'success',
                'message' => 'Configured SAT test created successfully.',
                'data' => $test,
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to generate configured structure', ['exception' => $e]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate configured structure. Please try again or contact support.',
            ], 500);
        }
    }

    public function cloneTest(Request $request, $id)
    {
        $originalTest = Test::visibleTo(auth()->user())->findOrFail($id);

        try {
            $test = $this->testManagement->cloneTest((int) $id, auth()->id());

            return response()->json([
                'status' => 'success',
                'message' => 'Test cloned successfully.',
                'data' => $test,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to clone test', ['exception' => $e]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clone test. Please try again or contact support.',
            ], 500);
        }
    }

    public function convertToNormal(Test $test, TestContentLockService $locks)
    {
        $this->authorize('update', $test);
        $locks->ensureUnlocked($test);
        if ($test->test_type !== Test::TYPE_ADAPTIVE_FULL) {
            throw ValidationException::withMessages(['test_type' => 'Only Adaptive Full drafts can be converted.']);
        }
        if ($test->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Return the test to draft before converting its type.']);
        }
        if ($test->userTests()->exists()) {
            throw ValidationException::withMessages(['test_type' => 'Test type cannot change after student attempts exist. Clone the test into a new draft.']);
        }
        $test->load('sections.modules');
        if ($test->sections->count() !== 2 || $test->sections->contains(fn ($section) => $section->modules->where('module_number', 1)->count() !== 1
            || $section->modules->where('module_number', 2)->count() !== 1)) {
            throw ValidationException::withMessages(['test_structure' => 'Keep exactly one Module 2 in each section before converting to Normal Full.']);
        }

        $test->update(['test_type' => Test::TYPE_FULL]);

        return response()->json(['status' => 'success', 'message' => 'Draft converted to Normal Full.', 'data' => $test->fresh()]);
    }

    public function cloneModule(Request $request, $id)
    {
        $originalModule = Module::visibleTo(auth()->user())->findOrFail($id);

        $sectionId = $request->input('section_id');
        if ($sectionId) {
            $section = Section::findOrFail($sectionId);
            app(\App\Services\TestContentLockService::class)->ensureUnlocked($section->test);
            if (auth()->user()->role === 'teacher' && $section->created_by !== auth()->id()) {
                abort(403, 'Unauthorized. You do not own the target section.');
            }
        }

        try {
            $module = $this->testManagement->cloneModule((int) $id, $sectionId ? (int) $sectionId : null, auth()->id());

            return response()->json([
                'status' => 'success',
                'message' => 'Module cloned successfully.',
                'data' => $module,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to clone module', ['exception' => $e]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clone module. Please try again or contact support.',
            ], 500);
        }
    }
}
