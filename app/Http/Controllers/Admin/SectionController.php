<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Test;
use App\Services\TestManagementService;
use App\Http\Requests\Admin\StoreSectionRequest;
use App\Http\Requests\Admin\UpdateSectionRequest;
use App\Http\Requests\Admin\LinkModuleRequest;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    public function linkModule(LinkModuleRequest $request)
    {
        $validated = $request->validated();

        $module = Module::findOrFail($validated['module_id']);
        if (auth()->user()->role === 'teacher') {
            if ($module->created_by !== auth()->id() && !$module->is_public && !($module->section && $module->section->is_public)) {
                if (!Module::visibleTo(auth()->user())->where('id', $module->id)->exists()) {
                    abort(403, 'Unauthorized. This module is private.');
                }
            }
        }

        if (empty($validated['section_id'])) {
            if (empty($validated['test_id']) || empty($validated['section_type'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Either Section ID or Test ID + Section Type is required.',
                ], 422);
            }

            $section = Section::firstOrCreate([
                'test_id' => $validated['test_id'],
                'type' => $validated['section_type'],
            ], [
                'name' => $validated['section_type'] === Section::TYPE_RW ? 'Reading and Writing' : 'Math',
                'order' => $validated['section_type'] === Section::TYPE_RW ? 1 : 2,
                'created_by' => auth()->id(),
                'is_public' => false,
            ]);
        } else {
            $section = Section::findOrFail($validated['section_id']);
        }

        $this->authorize('update', $section);

        if ($section->modules()->where('module_id', $module->id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This module is already linked to this section.',
            ], 422);
        }

        $section->modules()->attach($module->id);

        if ($section->test) {
            $section->test->refreshTotalDuration();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Module linked successfully!',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $section = Section::findOrFail($id);
        $this->authorize('delete', $section);

        try {
            $this->testManagement->deleteSection((int) $id, $request->boolean('delete_children'));
            return response()->json(['status' => 'success', 'message' => 'Section deleted.']);
        } catch (\Exception $e) {
            Log::error('Failed to delete section', ['exception' => $e]);
            return response()->json(['status' => 'error', 'message' => 'Failed to delete section. Please try again or contact support.'], 500);
        }
    }
}
