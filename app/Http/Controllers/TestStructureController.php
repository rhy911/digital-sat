<?php

namespace App\Http\Controllers;

use App\Models\Test;
use App\Models\Module;
use App\Models\Section;
use App\Services\TestManagementService;
use Illuminate\Http\Request;

class TestStructureController extends Controller
{
    protected TestManagementService $testManagement;

    public function __construct(TestManagementService $testManagement)
    {
        $this->testManagement = $testManagement;
    }

    public function generateFullSatStructure(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'test_type' => 'sometimes|string|in:full_length,short_test,module_only',
        ]);

        $testType = $validated['test_type'] ?? 'full_length';

        try {
            $test = $this->testManagement->generateFullSatStructure($validated['title'], $testType, auth()->id());

            return response()->json([
                'status' => 'success',
                'message' => 'SAT Structure created successfully.',
                'data' => $test
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to generate structure', ['exception' => $e]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate structure. Please try again or contact support.'
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
                'data' => $test
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to clone test', ['exception' => $e]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clone test. Please try again or contact support.'
            ], 500);
        }
    }

    public function cloneModule(Request $request, $id)
    {
        $originalModule = Module::visibleTo(auth()->user())->findOrFail($id);

        $sectionId = $request->input('section_id');
        if ($sectionId) {
            $section = Section::findOrFail($sectionId);
            if (auth()->user()->role === 'teacher' && $section->created_by !== auth()->id()) {
                abort(403, 'Unauthorized. You do not own the target section.');
            }
        }

        try {
            $module = $this->testManagement->cloneModule((int) $id, $sectionId ? (int) $sectionId : null, auth()->id());

            return response()->json([
                'status' => 'success',
                'message' => 'Module cloned successfully.',
                'data' => $module
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to clone module', ['exception' => $e]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clone module. Please try again or contact support.'
            ], 500);
        }
    }
}
