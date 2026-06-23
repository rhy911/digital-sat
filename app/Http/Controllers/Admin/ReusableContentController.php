<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use App\Services\TestContentCopyService;
use App\Services\TestContentLockService;
use Illuminate\Http\Request;

class ReusableContentController extends Controller
{
    public function __construct(
        private TestContentCopyService $copies,
        private TestContentLockService $locks,
    ) {}

    public function catalog(Request $request)
    {
        $tests = Test::visibleTo($request->user())
            ->where('title', '!=', 'Test Preview')
            ->with(['sections.modules' => fn ($query) => $query->withCount('questions')])
            ->latest()->limit(100)->get();

        return response()->json(['tests' => $tests]);
    }

    public function deriveSection(Request $request, Section $section)
    {
        $this->authorizeSourceSection($request, $section);
        $validated = $request->validate(['title' => 'required|string|max:255']);
        $test = $this->copies->deriveFromSection($section, $validated['title'], $request->user()->id);

        return response()->json($this->result($test), 201);
    }

    public function deriveModule(Request $request, Module $module)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'source_section_id' => 'required|integer|exists:sections,id',
        ]);
        $section = Section::findOrFail($validated['source_section_id']);
        abort_unless($section->modules()->whereKey($module->id)->exists(), 422, 'Module is not attached to the selected section.');
        $this->authorizeSourceSection($request, $section);
        $test = $this->copies->deriveFromModule($module, $section, $validated['title'], $request->user()->id);

        return response()->json($this->result($test), 201);
    }

    public function reuseSection(Request $request, Section $section)
    {
        $this->authorizeSourceSection($request, $section);
        $destination = $this->destination($request);
        $copy = $this->copies->copySection($section, $destination, $request->user()->id);

        return response()->json($this->result($destination, $copy->id), 201);
    }

    public function reuseModule(Request $request, Module $module)
    {
        $validated = $request->validate([
            'destination_test_id' => 'required|integer|exists:tests,id',
            'source_section_id' => 'required|integer|exists:sections,id',
        ]);
        $sourceSection = Section::findOrFail($validated['source_section_id']);
        abort_unless($sourceSection->modules()->whereKey($module->id)->exists(), 422, 'Module is not attached to the selected section.');
        $this->authorizeSourceSection($request, $sourceSection);
        $destination = $this->destination($request);
        $targetSection = $destination->sections()->where('type', $sourceSection->type)->first();
        if (! $targetSection) {
            $targetSection = Section::create([
                'test_id' => $destination->id,
                'name' => $sourceSection->name,
                'type' => $sourceSection->type,
                'order' => $sourceSection->order,
                'created_by' => $request->user()->id,
                'is_public' => false,
            ]);
        }
        $copy = $this->copies->copyModule($module, $targetSection, $request->user()->id);
        $destination->refreshTotalDuration();

        return response()->json($this->result($destination, $copy->id), 201);
    }

    private function destination(Request $request): Test
    {
        $validated = $request->validate(['destination_test_id' => 'required|integer|exists:tests,id']);
        $test = Test::findOrFail($validated['destination_test_id']);
        $this->authorize('update', $test);
        abort_unless($test->status === 'draft', 409, 'Destination test must be a draft.');
        $this->locks->ensureUnlocked($test);

        return $test;
    }

    private function authorizeSourceSection(Request $request, Section $section): void
    {
        $visible = Test::visibleTo($request->user())->whereKey($section->test_id)->exists();
        abort_unless($visible, 403, 'Source content is not available to you.');
    }

    private function result(Test $test, ?int $resourceId = null): array
    {
        return [
            'status' => 'success',
            'data' => $test->fresh('sections.modules'),
            'resource_id' => $resourceId,
            'builder_target' => route('home-dashboard.index'),
        ];
    }
}
