<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScoreConversionSet;
use App\Models\Test;
use App\Services\FormScoringAuditService;
use App\Services\TestContentLockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScoreConversionController extends Controller
{
    public function store(Request $request, Test $test)
    {
        $this->authorize('update', $test);
        app(TestContentLockService::class)->ensureUnlocked($test);
        if ($test->test_type !== Test::TYPE_FULL) {
            throw ValidationException::withMessages(['test' => 'Raw score conversion tables are available only for Normal Full tests. Adaptive Full uses the system IRT mapping.']);
        }
        $validated = $request->validate([
            'source_name' => 'required|string|max:255',
            'source_url' => 'nullable|url|max:2048',
            'notes' => 'nullable|string|max:5000',
            'rows' => 'required|array|min:1|max:500',
            'rows.*.section_type' => 'required|in:reading_writing,math',
            'rows.*.raw_score' => 'required|integer|min:0|max:100',
            'rows.*.scaled_score' => 'required|integer|min:200|max:800|multiple_of:10',
        ]);

        $set = DB::transaction(function () use ($test, $validated) {
            $version = ((int) $test->scoreConversionSets()->lockForUpdate()->max('version')) + 1;
            $rows = collect($validated['rows'])
                ->map(fn ($row) => $row + ['m2_difficulty' => 'standard'])
                ->sortBy([['section_type', 'asc'], ['raw_score', 'asc']])
                ->values();
            if ($rows->unique(fn ($row) => "{$row['section_type']}:{$row['raw_score']}")->count() !== $rows->count()) {
                throw ValidationException::withMessages(['rows' => 'Conversion rows contain duplicate section and raw-score combinations.']);
            }

            $set = ScoreConversionSet::create([
                'test_id' => $test->id,
                'version' => $version,
                'status' => ScoreConversionSet::STATUS_DRAFT,
                'source_name' => $validated['source_name'],
                'source_url' => $validated['source_url'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'checksum' => hash('sha256', json_encode($rows->all(), JSON_THROW_ON_ERROR)),
            ]);
            $set->rows()->createMany($rows->map(fn ($row) => $row + ['test_id' => null])->all());

            return $set->load('rows');
        });

        return response()->json(['status' => 'success', 'data' => $set], 201);
    }

    public function approve(ScoreConversionSet $scoreConversionSet, FormScoringAuditService $audit, TestContentLockService $locks)
    {
        $test = $scoreConversionSet->test;
        $this->authorize('update', $test);
        if ($scoreConversionSet->status !== ScoreConversionSet::STATUS_DRAFT) {
            throw ValidationException::withMessages(['score_conversion' => 'Only draft conversion sets can be approved.']);
        }

        $report = $audit->audit($test, $scoreConversionSet);
        if (! $report['eligible']) {
            throw ValidationException::withMessages(['score_conversion' => $report['errors']]);
        }

        DB::transaction(function () use ($scoreConversionSet, $test, $report) {
            ScoreConversionSet::where('test_id', $test->id)
                ->where('status', ScoreConversionSet::STATUS_APPROVED)
                ->update(['status' => ScoreConversionSet::STATUS_RETIRED]);
            $scoreConversionSet->update([
                'status' => ScoreConversionSet::STATUS_APPROVED,
                'form_checksum' => $report['form_checksum'],
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });
        $locks->syncLock($test);

        return response()->json(['status' => 'success', 'data' => $scoreConversionSet->fresh('rows')]);
    }
}
