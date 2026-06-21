<?php

namespace App\Http\Controllers\Engine\Concerns;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;

trait ResolvesRouting
{
    protected function resolveNextModule(Module $module, Section $section, Test $test, $user, bool $allowPrivate = false): array
    {
        if ((int) $module->module_number === 1) {
            $nextModuleQuery = $section->modules();
            if (!$allowPrivate) $nextModuleQuery->visibleTo($user);
            $nextModule = $nextModuleQuery
                ->where('module_number', 2)
                ->reorder()
                ->orderByRaw('CASE WHEN difficulty_level = ? THEN 0 ELSE 1 END', [Module::DIFFICULTY_HARD])
                ->orderBy('modules.order')
                ->first();

            if ($nextModule) {
                return [$nextModule, $section];
            }
        }

        $nextSection = $test->sections()
            ->where('order', '>', $section->order)
            ->orderBy('order')
            ->first();

        if (! $nextSection) {
            return [null, null];
        }

        $nextModuleQuery = $nextSection->modules();
        if (!$allowPrivate) $nextModuleQuery->visibleTo($user);
        $nextModule = $nextModuleQuery
            ->where('module_number', 1)
            ->reorder()
            ->orderBy('modules.order')
            ->first();

        return [$nextModule, $nextModule ? $nextSection : null];
    }
}
