<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Test;
use App\Models\UserTest;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AttemptProgressionService
{
    public function firstModule(Test $test): ?Module
    {
        $section = $test->sections()->orderBy('order')->first();

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

        $module = $this->firstModule($test);
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

    public function advance(UserTest $attempt, array $result): ?Module
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
            'current_module_started_at' => null,
            'current_module_elapsed_seconds' => 0,
        ])->save();

        return $nextModule;
    }
}
