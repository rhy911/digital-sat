<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Section;
use App\Models\UserTest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class AssignmentModuleTimingService
{
    public function durationSeconds(Module $module): int
    {
        $minutes = $module->duration_minutes
            ?? ($module->section?->type === Section::TYPE_MATH ? 35 : 32);

        return max(0, (int) $minutes * 60);
    }

    public function timing(UserTest $userTest, Module $module, ?Carbon $now = null): array
    {
        $durationSeconds = $this->durationSeconds($module);
        $startedAt = $userTest->current_module_started_at;

        if (!$startedAt) {
            return [
                'duration_seconds' => $durationSeconds,
                'elapsed_seconds' => 0,
                'remaining_seconds' => $durationSeconds,
                'expired' => false,
            ];
        }

        $now ??= now();
        $elapsedSeconds = max(0, $now->getTimestamp() - $startedAt->getTimestamp());
        $elapsedSeconds = min($durationSeconds, $elapsedSeconds);
        $remainingSeconds = max(0, $durationSeconds - $elapsedSeconds);

        return [
            'duration_seconds' => $durationSeconds,
            'elapsed_seconds' => $elapsedSeconds,
            'remaining_seconds' => $remainingSeconds,
            'expired' => $remainingSeconds === 0,
        ];
    }

    /**
     * The wall-clock moment this module's time runs out, or null if its clock has
     * not started. This is the instant the module is treated as submitted when the
     * student is not there to submit it themselves, so it must be derived from the
     * stored start date only — never from "now" — or a sweeper running late would
     * push the deadline forward and hand out extra time.
     */
    public function deadline(UserTest $userTest, Module $module): ?CarbonInterface
    {
        if (! $userTest->current_module_started_at) {
            return null;
        }

        return $userTest->current_module_started_at->copy()
            ->addSeconds($this->durationSeconds($module));
    }

    public function syncElapsed(UserTest $userTest, Module $module): array
    {
        $timing = $this->timing($userTest, $module);
        $userTest->current_module_elapsed_seconds = $timing['elapsed_seconds'];
        $userTest->save();

        return $timing;
    }
}
