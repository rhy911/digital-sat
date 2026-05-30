<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use App\Services\TestManagementService;
use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateModuleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ModuleController extends Controller
{
    protected TestManagementService $testManagement;

    public function __construct(TestManagementService $testManagement)
    {
        $this->testManagement = $testManagement;
    }

    public function storeModule(StoreModuleRequest $request)
    {
        $validated = $request->validated();

        if (empty($validated['section_id']) && !empty($validated['test_id']) && !empty($validated['section_type'])) {
            $test = Test::findOrFail($validated['test_id']);
            if (auth()->user()->role === 'teacher' && $test->created_by !== auth()->id()) {
                abort(403, 'Unauthorized. You do not own the parent test.');
            }

            $section = Section::firstOrCreate([
                'test_id' => $validated['test_id'],
                'type' => $validated['section_type'],
            ], [
                'name' => $validated['section_type'] === Section::TYPE_RW ? 'Reading and Writing' : 'Math',
                'order' => $validated['section_type'] === Section::TYPE_RW ? 1 : 2,
                'created_by' => auth()->id(),
                'is_public' => $request->boolean('is_public', false),
            ]);
            $validated['section_id'] = $section->id;
        }

        if (!empty($validated['section_id'])) {
            $section = Section::findOrFail($validated['section_id']);
            if (auth()->user()->role === 'teacher' && $section->created_by !== auth()->id()) {
                abort(403, 'Unauthorized. You do not own the parent section.');
            }

            $baseOrder = (($section->order - 1) * 2) + (int) $validated['module_number'];
            $existingMax = Module::where('section_id', $section->id)
                ->where('module_number', $validated['module_number'])
                ->max('order');
            $validated['order'] = $existingMax !== null ? ((int) $existingMax + 1) : $baseOrder;
        } else {
            $validated['order'] = 1;
        }

        if (empty($validated['key'])) {
            $validated['key'] = 'MOD_' . strtoupper(Str::random(8));
        }

        $validated['created_by'] = auth()->id();
        $validated['is_public'] = $request->boolean('is_public', false);
        $module = Module::create($validated);

        if (!empty($validated['section_id'])) {
            $module->sections()->syncWithoutDetaching([$validated['section_id']]);
            
            if ($module->section && $module->section->test) {
                $module->section->test->refreshTotalDuration();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Module created successfully',
            'data' => $module,
        ], 201);
    }

    public function updateModule(UpdateModuleRequest $request, $id)
    {
        $module = Module::findOrFail($id);
        $this->authorize('update', $module);

        $validated = $request->validated();
        if (isset($validated['is_public'])) {
            $validated['is_public'] = filter_var($validated['is_public'], FILTER_VALIDATE_BOOLEAN);
        }
        $module->update($validated);
        
        if ($module->section && $module->section->test) {
            $module->section->test->refreshTotalDuration();
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Module updated successfully',
            'data' => $module,
        ]);
    }

    public function deleteModule(Request $request, $id)
    {
        $module = Module::findOrFail($id);
        $this->authorize('delete', $module);

        try {
            $this->testManagement->deleteModule((int) $id, $request->boolean('delete_children'));
            return response()->json(['status' => 'success', 'message' => 'Module deleted.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
