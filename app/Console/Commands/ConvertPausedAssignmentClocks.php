<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\Module;
use App\Models\UserTest;
use App\Models\UserTestModuleSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConvertPausedAssignmentClocks extends Command
{
    protected $signature = 'assignments:convert-paused-clocks
                            {--dry-run : Report changes without writing}
                            {--max-assignments=100 : Bound the number of assignments processed}';

    protected $description = 'Convert legacy pausable assignments to continuous clocks and preserve their remaining time';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('max-assignments'));
        $converted = 0;
        $attempts = 0;

        Assignment::query()->where('clock_mode', 'paused')->orderBy('id')->limit($limit)->pluck('id')
            ->each(function (int $assignmentId) use ($dryRun, &$converted, &$attempts) {
                $counts = DB::transaction(function () use ($assignmentId, $dryRun) {
                    $assignment = Assignment::query()->whereKey($assignmentId)->lockForUpdate()->first();
                    if (! $assignment || $assignment->clock_mode !== 'paused') {
                        return [0, 0];
                    }

                    $activeAttempts = UserTest::query()
                        ->where('assignment_id', $assignment->id)
                        ->where('status', 'in_progress')
                        ->lockForUpdate()
                        ->get();

                    foreach ($activeAttempts as $attempt) {
                        $module = Module::find($attempt->current_module_id);
                        if (! $module) {
                            continue;
                        }

                        $elapsed = (int) ($attempt->current_module_elapsed_seconds ?? 0);
                        $receipt = UserTestModuleSubmission::query()
                            ->where('user_test_id', $attempt->id)
                            ->where('issued_next_module_id', $module->id)
                            ->latest('id')
                            ->first();
                        $result = $receipt ? ($receipt->result ?? []) : [];
                        $breakEndsAt = ! empty($result['is_section_break'])
                            ? ($result['break_ends_at'] ?? $receipt?->submitted_at?->copy()
                                ->addSeconds((int) ($result['break_duration_seconds'] ?? 600)))
                            : null;
                        $anchor = $attempt->current_module_started_at
                            ? $attempt->current_module_started_at->copy()->subSeconds($elapsed)
                            : ($breakEndsAt
                                ? Carbon::parse($breakEndsAt)
                                : $attempt->created_at->copy()->subSeconds($elapsed));

                        if (! $dryRun) {
                            $attempt->forceFill([
                                'current_module_started_at' => $anchor,
                                'current_module_elapsed_seconds' => 0,
                                'current_module_paused' => false,
                            ])->save();
                        }
                    }

                    if (! $dryRun) {
                        $assignment->clock_mode = 'chained';
                        $assignment->save();
                    }

                    return [1, $activeAttempts->count()];
                });

                $converted += $counts[0];
                $attempts += $counts[1];
                if ($counts[0]) {
                    $this->line(($dryRun ? 'Would convert' : 'Converted')." assignment {$assignmentId} ({$counts[1]} active attempt(s))");
                }
            });

        $this->info(($dryRun ? 'Would convert ' : 'Converted ')."{$converted} assignment(s) and {$attempts} active attempt(s).");

        return self::SUCCESS;
    }
}
