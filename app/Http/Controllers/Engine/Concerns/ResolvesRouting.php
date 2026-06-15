<?php

namespace App\Http\Controllers\Engine\Concerns;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;

trait ResolvesRouting
{
    protected function resolveNextModule(Module $module, Section $section, Test $test, $user): array
    {
        if ((int) $module->module_number === 1) {
            $nextModule = $section->modules()
                ->visibleTo($user)
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

        $nextModule = $nextSection->modules()
            ->visibleTo($user)
            ->where('module_number', 1)
            ->reorder()
            ->orderBy('modules.order')
            ->first();

        return [$nextModule, $nextModule ? $nextSection : null];
    }
}
