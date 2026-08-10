<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class QueueHealthCommand extends Command
{
    protected $signature = 'sat:queue-health {--json : Emit a single JSON line instead of a table}';

    protected $description = 'Snapshot queue depth, oldest job age, recent failures and live submit locks.';

    /**
     * Four cheap COUNTs — safe to run every minute, including during an exam.
     *
     * Worker liveness is measured WITHOUT shelling out: the production host has
     * `shell_exec` disabled, so `pgrep` is not an option. `oldest_pending_age_s`
     * is the better signal anyway — if unreserved jobs are piling up and ageing,
     * nothing is consuming the queue, which is exactly the question worth asking.
     */
    public function handle(): int
    {
        $oldestPending = DB::table('jobs')->whereNull('reserved_at')->min('created_at');
        $lockTtl = (int) config('scoring.lock_ttl', 240);

        $snapshot = [
            'pending' => DB::table('jobs')->whereNull('reserved_at')->count(),
            'reserved' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
            'oldest_pending_age_s' => $oldestPending ? max(0, time() - (int) $oldestPending) : 0,
            'failed_last_hour' => DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHour())
                ->count(),
            'submit_locks' => $this->submitLockCount(),
            'lock_ttl_s' => $lockTtl,
            'at' => now()->toIso8601String(),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($snapshot));
        } else {
            $this->table(array_keys($snapshot), [array_values($snapshot)]);
        }

        Log::channel('queue')->info('queue_health', $snapshot);

        // A backlog older than the lock TTL means invariant I2 is being violated
        // right now: locks are lapsing before their jobs run, which is how
        // duplicate jobs get dispatched. Non-zero exit makes this usable as an alarm.
        if ($snapshot['oldest_pending_age_s'] > $lockTtl) {
            $this->error("Oldest pending job is {$snapshot['oldest_pending_age_s']}s old, above lock_ttl {$lockTtl}s.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Only meaningful on the database cache store; returns null elsewhere rather
     * than guessing.
     */
    private function submitLockCount(): ?int
    {
        if (config('cache.default') !== 'database' || ! Schema::hasTable('cache_locks')) {
            return null;
        }

        return DB::table('cache_locks')->where('key', 'like', 'module_submit_lock_%')->count();
    }
}
