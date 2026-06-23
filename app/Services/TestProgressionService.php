<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use Illuminate\Support\Facades\Schema;

class TestProgressionService
{
    public function __construct(
        private TestStructureService $structures,
        private SatScoringService $scoring,
        private ScoreConversionService $conversions,
        private AdaptiveScoreConversionService $adaptiveConversions,
    ) {}

    public function submit(UserTest $attempt, Module $submitted): array
    {
        $test = Test::with('sections.modules.questions')->findOrFail($attempt->test_id);
        $shape = $this->structures->validate($test);
        $section = $shape['sections']->first(fn ($candidate) => $candidate->modules->contains('id', $submitted->id));
        if (! $section) {
            throw new \RuntimeException('Submitted module is outside the validated test structure.');
        }

        $flow = $shape['flows']->get($section->id);
        if ($flow === TestStructureService::FLOW_ADAPTIVE && (int) $submitted->module_number === 1) {
            return $this->routeAdaptive($attempt, $test, $section, $submitted);
        }

        if ($flow === TestStructureService::FLOW_LINEAR) {
            $modules = $this->structures->orderedModules($section);
            $index = $modules->search(fn ($module) => (int) $module->id === (int) $submitted->id);
            if ($index !== false && $modules->has($index + 1)) {
                return $this->nextModuleResult($modules->get($index + 1), 'Moving to the next module.');
            }
        }

        return $this->advanceSectionOrComplete($attempt, $test, $section, $shape['sections']);
    }

    private function routeAdaptive(UserTest $attempt, Test $test, Section $section, Module $module): array
    {
        $responses = $this->completeResponsesForModule($attempt, $module);
        $theta = $this->scoring->estimateTheta($responses);
        $requestedPath = $this->scoring->routeModule2($theta);
        $next = $section->modules->first(fn ($candidate) => (int) $candidate->module_number === 2 && $candidate->difficulty_level === $requestedPath);
        $usedFallback = false;
        if (! $next && $test->test_type !== Test::TYPE_ADAPTIVE_FULL) {
            $next = $section->modules->first(fn ($candidate) => (int) $candidate->module_number === 2 && $candidate->difficulty_level !== $requestedPath);
            $usedFallback = $next !== null;
        }
        if (! $next) {
            throw new \RuntimeException('Adaptive test is missing the routed Module 2 path. Convert the draft to Normal Full or restore both branches.');
        }

        $actualPath = $next->difficulty_level;
        $attempt->forceFill($section->type === Section::TYPE_RW ? ['rw_m2_path' => $actualPath] : ['math_m2_path' => $actualPath])->save();

        $result = $this->nextModuleResult($next, "Module 1 submitted. Routed to {$actualPath} Module 2.") + [
            'path' => $requestedPath,
            'actual_path' => $actualPath,
        ];
        if ($usedFallback) {
            $result['fallback_module_id'] = $next->ulid;
        }

        return $result;
    }

    private function advanceSectionOrComplete(UserTest $attempt, Test $test, Section $section, $sections): array
    {
        $nextSection = $sections->first(fn ($candidate) => (int) $candidate->order > (int) $section->order);
        if ($nextSection) {
            $next = $this->structures->orderedModules($nextSection)->first();

            return $this->nextModuleResult($next, 'Section completed. Moving to the next section.');
        }

        $this->finalize($attempt, $test);

        return [
            'status' => 'success',
            'test_completed' => true,
            'redirect_url' => route('home'),
            'results_url' => route('my-practice.score', $attempt),
            'message' => 'Test completed.',
        ];
    }

    private function nextModuleResult(Module $module, string $message): array
    {
        return ['status' => 'success', 'next_module_id' => $module->ulid, 'message' => $message];
    }

    private function finalize(UserTest $attempt, Test $test): void
    {
        if ($attempt->user?->role === 'admin') {
            $attempt->update($this->completionFields());

            return;
        }

        if ($test->test_type === Test::TYPE_ADAPTIVE_FULL) {
            $this->finalizeAdaptive($attempt, $test);

            return;
        }

        if ($test->test_type === Test::TYPE_FULL) {
            $this->finalizeNormal($attempt, $test);

            return;
        }

        $rwResponses = $this->responsesForSection($attempt, $test->sections->firstWhere('type', Section::TYPE_RW));
        $mathResponses = $this->responsesForSection($attempt, $test->sections->firstWhere('type', Section::TYPE_MATH));
        $rwAbility = $rwResponses->isEmpty() ? null : $this->scoring->estimateAbility($rwResponses);
        $mathAbility = $mathResponses->isEmpty() ? null : $this->scoring->estimateAbility($mathResponses);
        $attempt->update($this->completionFields() + [
            'score_reading_writing' => null,
            'score_math' => null,
            'total_score' => null,
            'rw_theta' => $rwAbility ? round($rwAbility['theta'], 3) : null,
            'math_theta' => $mathAbility ? round($mathAbility['theta'], 3) : null,
            'rw_theta_se' => $rwAbility ? round($rwAbility['se'], 3) : null,
            'math_theta_se' => $mathAbility ? round($mathAbility['se'], 3) : null,
            'scoring_method' => $rwAbility['method'] ?? $mathAbility['method'] ?? null,
            'score_conversion_set_id' => null,
            'score_conversion_version' => null,
            'score_estimate_kind' => null,
        ]);
    }

    private function finalizeAdaptive(UserTest $attempt, Test $test): void
    {
        $rw = $test->sections->firstWhere('type', Section::TYPE_RW);
        $math = $test->sections->firstWhere('type', Section::TYPE_MATH);
        $rwScore = $this->scoreAdaptiveSection($attempt, $rw, $attempt->rw_m2_path);
        $mathScore = $this->scoreAdaptiveSection($attempt, $math, $attempt->math_m2_path);
        $rwConversion = $this->adaptiveConversions->convert($rwScore['theta'], $rwScore['theta_se']);
        $mathConversion = $this->adaptiveConversions->convert($mathScore['theta'], $mathScore['theta_se']);
        $total = $this->adaptiveConversions->totalRange($rwConversion, $mathConversion);
        $fields = $this->completionFields() + [
            'score_reading_writing' => $rwConversion['scaled_score'],
            'score_reading_writing_lower' => $rwConversion['lower'],
            'score_reading_writing_upper' => $rwConversion['upper'],
            'score_math' => $mathConversion['scaled_score'],
            'score_math_lower' => $mathConversion['lower'],
            'score_math_upper' => $mathConversion['upper'],
            'rw_theta' => $rwScore['theta'],
            'math_theta' => $mathScore['theta'],
            'rw_theta_se' => $rwScore['theta_se'],
            'math_theta_se' => $mathScore['theta_se'],
            'scoring_method' => $rwScore['method'],
            'total_score' => $total['score'],
            'total_score_lower' => $total['lower'],
            'total_score_upper' => $total['upper'],
            'score_conversion_set_id' => null,
            'score_conversion_version' => $rwConversion['conversion_version'],
            'score_estimate_kind' => $rwConversion['estimate_kind'],
        ];
        $attempt->update($fields);
    }

    private function finalizeNormal(UserTest $attempt, Test $test): void
    {
        $rw = $this->allResponsesForSection($attempt, $test->sections->firstWhere('type', Section::TYPE_RW));
        $math = $this->allResponsesForSection($attempt, $test->sections->firstWhere('type', Section::TYPE_MATH));
        $fields = $this->completionFields() + [
            'score_reading_writing' => null, 'score_math' => null, 'total_score' => null,
            'rw_theta' => null, 'math_theta' => null, 'rw_theta_se' => null, 'math_theta_se' => null,
            'scoring_method' => 'raw_table_v1', 'score_conversion_set_id' => null,
            'score_conversion_version' => null, 'score_estimate_kind' => null,
        ];
        try {
            $rwConversion = $this->conversions->convert($test, Section::TYPE_RW, $rw->where('is_correct', true)->count(), $rw->count());
            $mathConversion = $this->conversions->convert($test, Section::TYPE_MATH, $math->where('is_correct', true)->count(), $math->count());
            if ($rwConversion['conversion_set_id'] !== $mathConversion['conversion_set_id']) {
                throw new \RuntimeException('Sections resolved against different conversion sets.');
            }
            $fields['score_reading_writing'] = $rwConversion['scaled_score'];
            $fields['score_math'] = $mathConversion['scaled_score'];
            $fields['total_score'] = $rwConversion['scaled_score'] + $mathConversion['scaled_score'];
            $fields['score_conversion_set_id'] = $rwConversion['conversion_set_id'];
            $fields['score_conversion_version'] = $rwConversion['conversion_version'];
            $fields['score_estimate_kind'] = $rwConversion['estimate_kind'];
        } catch (\RuntimeException) {
            // Invalid normal forms complete with accuracy data but no manufactured score.
        }
        $attempt->update($fields);
    }

    private function completionFields(): array
    {
        return ['status' => 'completed', 'completed_at' => now(), 'current_module_id' => null];
    }

    private function scoreAdaptiveSection(UserTest $attempt, Section $section, ?string $path): array
    {
        $m1 = $section->modules->first(fn ($module) => (int) $module->module_number === 1);
        $m2 = $section->modules->first(fn ($module) => (int) $module->module_number === 2 && $module->difficulty_level === $path);

        if (! $m1 || ! $m2) {
            throw new \RuntimeException('Adaptive section is missing the submitted route.');
        }

        return $this->scoring->scoreSection(
            $this->completeResponsesForModule($attempt, $m1),
            $this->completeResponsesForModule($attempt, $m2),
            $path
        );
    }

    private function responsesForSection(UserTest $attempt, ?Section $section)
    {
        if (! $section) {
            return collect();
        }

        return $section->modules->flatMap(fn ($module) => $this->responsesForModule($attempt, $module))
            ->filter(fn ($response) => ! $response->question?->is_pretest)->values();
    }

    private function allResponsesForSection(UserTest $attempt, ?Section $section)
    {
        if (! $section) {
            return collect();
        }

        return $section->modules->flatMap(fn ($module) => $this->responsesForModule($attempt, $module))->values();
    }

    private function responsesForModule(UserTest $attempt, Module $module)
    {
        return UserTestAnswer::where('user_test_id', $attempt->id)
            ->where('module_id', $module->id)
            ->with('question:id,irt_a,irt_b,irt_c,is_pretest')
            ->get();
    }

    private function completeResponsesForModule(UserTest $attempt, Module $module)
    {
        $responses = $this->responsesForModule($attempt, $module);
        if ($responses->count() !== $module->questions->count()) {
            throw new \RuntimeException('Submitted module does not contain a response record for every presented question.');
        }

        return $responses;
    }
}
