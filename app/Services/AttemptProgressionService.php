<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Test;
use App\Models\UserTest;
use Carbon\CarbonInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AttemptProgressionService
{
    public function firstModule(Test $test, ?UserTest $attempt = null): ?Module
    {
        if ($attempt && $attempt->attempt_type === 'section' && $attempt->section_type) {
            $sectionType = $attempt->section_type === 'reading_writing' ? \App\Models\Section::TYPE_RW : \App\Models\Section::TYPE_MATH;
            $section = $test->sections()->where('type', $sectionType)->first();
        } else {
            $section = $test->sections()->orderBy('order')->first();
        }

        return $section?->modules()
            ->reorder()
            ->orderBy('modules.order')
            ->orderBy('modules.id')
            ->first();
    }

    public function issueInitialModule(UserTest $attempt, Test $test): Module
    {
        if ($attempt->current_module_id) {
            return $attempt->currentModule()->firstOrFail();
        }

        $module = $this->firstModule($test, $attempt);
        abort_unless($module, 422, 'Test has no module.');

        $attempt->forceFill([
            'current_module_id' => $module->id,
            'current_module_started_at' => null,
            'current_module_elapsed_seconds' => 0,
        ])->save();

        return $module;
    }

    public function assertIssued(UserTest $attempt, Module $module): void
    {
        if ((int) $attempt->current_module_id !== (int) $module->id) {
            throw new ConflictHttpException('This module is not active for the test attempt.');
        }
    }

    /**
     * Issue the next module and start its clock.
     *
     * Assignment attempts run on a CHAINED clock: the next module's timer starts
     * the moment the previous one ends, whether or not the student ever opens the
     * page. A null start date here is what used to make an unopened module
     * unexpirable, so a student who closed the tab left the attempt in_progress
     * forever — see AssignmentAttemptTimeoutService.
     *
     * $startNextAt is the previous module's true deadline. The timeout sweeper
     * passes it so a cascade that runs late produces the same module boundaries as
     * one that ran on time; cron lateness must never hand out extra test time. A
     * live student's submit passes null and gets now(), which is that same moment.
     *
     * Practice attempts keep the null: they are allowed to pause while away and
     * resume from current_module_elapsed_seconds (SessionController::show).
     *
     * @param  array<string, mixed>  $result
     */
    public function advance(UserTest $attempt, array $result, ?CarbonInterface $startNextAt = null): ?Module
    {
        if (! empty($result['test_completed'])) {
            $attempt->forceFill([
                'current_module_id' => null,
                'current_module_started_at' => null,
                'current_module_elapsed_seconds' => 0,
            ])->save();

            return null;
        }

        $nextModuleUlid = $result['next_module_id'] ?? null;
        if (! $nextModuleUlid) {
            throw new \RuntimeException('Successful scoring result did not issue a next module.');
        }

        $nextModule = Module::query()
            ->where('ulid', $nextModuleUlid)
            ->whereHas('sections', fn ($sections) => $sections->where('test_id', $attempt->test_id))
            ->firstOrFail();

        $attempt->forceFill([
            'current_module_id' => $nextModule->id,
            'current_module_started_at' => $attempt->assignment_id
                ? ($startNextAt ? $startNextAt->copy() : now())
                : null,
            'current_module_elapsed_seconds' => 0,
        ])->save();

        return $nextModule;
    }
}
