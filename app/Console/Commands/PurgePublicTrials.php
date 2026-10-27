<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserTest;
use App\Services\AttemptEngine;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PurgePublicTrials extends Command
{
    protected $signature = 'public-trial:purge {--dry-run : Report eligible rows without changing data} {--initialize : Set legacy retention deadlines only}';

    protected $description = 'Initialize or remove expired public guest trial data.';

    public function handle(): int
    {
        if ($this->option('initialize')) {
            return $this->initializeDeadlines();
        }
        if ($this->option('dry-run')) {
            $this->info('Expired eligible attempts: '.UserTest::expiredGuestPublicTrials()->count());
            $this->info('Purgeable empty guest identities: '.$this->countPurgeableGuests());

            return self::SUCCESS;
        }
        if (! config('public_trial.retention.cleanup_enabled')) {
            $this->warn('Physical cleanup is disabled. Set PUBLIC_TRIAL_CLEANUP_ENABLED=true after rollout verification.');

            return self::SUCCESS;
        }

        return $this->purge();
    }

    private function initializeDeadlines(): int
    {
        $reference = now()->utc();
        $idle = $reference->copy()->addMinutes((int) config('public_trial.retention.legacy_grace_minutes', 30));
        $query = UserTest::eligibleGuestPublicTrials()->where(function ($deadlines) {
            $deadlines->whereNull('guest_trial_expires_at')->orWhereNull('guest_trial_absolute_expires_at');
        });
        $count = (clone $query)->count();
        if ($this->option('dry-run')) {
            $this->info("Legacy eligible attempts to initialize: {$count}; reference time: {$reference->toIso8601String()}");

            return self::SUCCESS;
        }

        $query->orderBy('user_tests.id')->chunkById(500, function ($attempts) use ($idle): void {
            foreach ($attempts as $attempt) {
                $createdAt = $attempt->created_at?->copy()->utc() ?? $idle->copy();
                $absolute = $createdAt->addHours((int) config('public_trial.retention.absolute_hours', 24));
                if ($absolute->lessThan($idle)) {
                    $absolute = $idle->copy();
                }
                DB::table('user_tests')->where('id', $attempt->id)->update([
                    'guest_trial_expires_at' => $idle->format('Y-m-d H:i:s'),
                    'guest_trial_absolute_expires_at' => $absolute->format('Y-m-d H:i:s'),
                ]);
            }
        }, 'id');
        $this->info("Initialized {$count} legacy public trial attempts.");

        return self::SUCCESS;
    }

    private function purge(): int
    {
        $started = microtime(true);
        $cursor = 0;
        $scanned = $deleted = $skipped = $usersDeleted = $failures = 0;
        $batchSize = (int) config('public_trial.retention.cleanup_batch_size', 100);
        $maxCandidates = (int) config('public_trial.retention.cleanup_max_candidates', 1000);
        $attemptCandidateLimit = max(0, $maxCandidates - min(100, $maxCandidates));
        $maxSeconds = (int) config('public_trial.retention.cleanup_max_seconds', 60);

        while ($scanned < $attemptCandidateLimit && microtime(true) - $started < $maxSeconds) {
            $batch = UserTest::expiredGuestPublicTrials()
                ->where('user_tests.id', '>', $cursor)
                ->orderBy('user_tests.id')
                ->limit(min($batchSize, $attemptCandidateLimit - $scanned))
                ->get(['user_tests.id', 'user_tests.user_id', 'user_tests.current_module_id']);
            if ($batch->isEmpty()) {
                break;
            }

            foreach ($batch as $candidate) {
                $cursor = (int) $candidate->id;
                $scanned++;
                try {
                    $removed = DB::transaction(function () use ($candidate): bool {
                        User::withTrashed()->whereKey($candidate->user_id)->lockForUpdate()->first();
                        $attempt = UserTest::whereKey($candidate->id)->lockForUpdate()->first();
                        if (! $attempt || ! $attempt->isGuestTrialExpired()) {
                            return false;
                        }

                        $modules = $attempt->moduleSubmissions()->pluck('module_id')->push($attempt->current_module_id)->filter()->unique();
                        $locks = [];
                        try {
                            foreach ($modules as $moduleId) {
                                $lock = Cache::lock("module_submit_lock_{$attempt->id}_{$moduleId}", (int) config('scoring.lock_ttl', 240));
                                if (! $lock->get()) {
                                    return false;
                                }
                                $locks[] = $lock;
                                if (Cache::has(AttemptEngine::submitMarkerKey((int) $attempt->id, (int) $moduleId))) {
                                    return false;
                                }
                            }

                            $attemptId = (int) $attempt->id;
                            $attempt->delete();
                            Cache::forget("scoring_result_{$attemptId}");
                            foreach ($modules as $moduleId) {
                                Cache::forget(AttemptEngine::submitMarkerKey($attemptId, (int) $moduleId));
                            }

                            return true;
                        } finally {
                            foreach ($locks as $held) {
                                $held->release();
                            }
                        }
                    });
                    $removed ? $deleted++ : $skipped++;
                } catch (\Throwable) {
                    $failures++;
                }
                if (microtime(true) - $started >= $maxSeconds || $scanned >= $attemptCandidateLimit) {
                    break;
                }
            }
        }

        $deadline = $started + $maxSeconds;
        [$usersDeleted, $userFailures, $guestScanned] = $this->purgeEmptyGuests(
            (int) max(0, $deadline - microtime(true)),
            max(0, $maxCandidates - $scanned),
        );
        $scanned += $guestScanned;
        $failures += $userFailures;
        $this->purgeExpiredPublicTrialCache($deadline);
        $duration = round(microtime(true) - $started, 3);
        Log::info('Public guest trial cleanup completed.', compact('scanned', 'deleted', 'skipped', 'usersDeleted', 'failures', 'duration'));
        $this->info("Scanned {$scanned}; deleted {$deleted} attempts; skipped {$skipped}; deleted {$usersDeleted} empty guest identities; failures {$failures} in {$duration}s.");

        return self::SUCCESS;
    }

    /** @return array{int, int, int} */
    private function purgeEmptyGuests(int $remainingSeconds, int $maximumCandidates): array
    {
        if ($remainingSeconds <= 0 || $maximumCandidates <= 0) {
            return [0, 0, 0];
        }
        $deleted = $failures = 0;
        $cursorKey = 'public_trial_guest_purge_cursor';
        $cursor = (int) Cache::get($cursorKey, 0);
        $scanned = 0;
        $deadline = microtime(true) + $remainingSeconds;
        $references = $this->userReferences();
        $artifactTables = ['sessions', 'notifications', 'personal_access_tokens'];

        while (microtime(true) < $deadline && $scanned < $maximumCandidates) {
            $guests = $this->emptyGuestCandidates()->where('id', '>', $cursor)->orderBy('id')
                ->limit(min(100, $maximumCandidates - $scanned))->get();
            if ($guests->isEmpty()) {
                if ($cursor !== 0) {
                    Cache::put($cursorKey, 0, now()->addDay());
                }
                break;
            }
            foreach ($guests as $guest) {
                if (microtime(true) >= $deadline) {
                    break 2;
                }
                $cursor = (int) $guest->id;
                $scanned++;
                Cache::put($cursorKey, $cursor, now()->addDay());
                try {
                    $removed = DB::transaction(function () use ($guest, $references, $artifactTables): bool {
                        $locked = User::withTrashed()->whereKey($guest->id)->lockForUpdate()->first();
                        if (! $locked || ! $locked->is_guest || UserTest::where('user_id', $locked->id)->exists()) {
                            return false;
                        }
                        if ($locked->created_at?->greaterThan($this->guestGraceCutoff())
                            || $this->hasProtectedGuestReference($locked, $references, $artifactTables)) {
                            return false;
                        }
                        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
                            DB::table('sessions')->where('user_id', $locked->id)->delete();
                        }
                        $locked->notifications()->delete();
                        $locked->tokens()->delete();
                        $locked->forceDelete();

                        return true;
                    });
                    if ($removed) {
                        $deleted++;
                    }
                } catch (\Throwable) {
                    $failures++;
                }
            }
        }

        return [$deleted, $failures, $scanned];
    }

    private function purgeExpiredPublicTrialCache(float $deadline): void
    {
        if (! Schema::hasTable('cache')) {
            return;
        }
        $deleted = 0;
        foreach (['cache', 'cache_locks'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            while (microtime(true) < $deadline && $deleted < 1000) {
                $expiredAt = now()->timestamp;
                $keys = DB::table($table)->where('expiration', '<=', $expiredAt)
                    ->where('key', 'like', '%public_trial%')->orderBy('key')->limit(100)->pluck('key');
                if ($keys->isEmpty()) {
                    break;
                }
                $deleted += DB::table($table)->whereIn('key', $keys)->where('expiration', '<=', $expiredAt)->delete();
            }
        }
    }

    private function countPurgeableGuests(): int
    {
        $references = $this->userReferences();
        $artifactTables = ['sessions', 'notifications', 'personal_access_tokens'];

        $guests = $this->emptyGuestCandidates()->get();

        return $guests->reject(fn (User $guest): bool => $this->hasProtectedGuestReference($guest, $references, $artifactTables))->count();
    }

    /**
     * @param array<int, object{TABLE_NAME: string, COLUMN_NAME: string}> $references
     * @param array<int, string> $artifactTables
     */
    private function hasProtectedGuestReference(User $guest, array $references, array $artifactTables): bool
    {
        foreach ($references as $reference) {
            if (in_array($reference->TABLE_NAME, $artifactTables, true) || $reference->TABLE_NAME === 'user_tests') {
                continue;
            }
            if (Schema::hasTable($reference->TABLE_NAME)
                && DB::table($reference->TABLE_NAME)->where($reference->COLUMN_NAME, $guest->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /** @return Builder<User> */
    private function emptyGuestCandidates(): Builder
    {
        return User::withTrashed()->where('is_guest', true)->whereDoesntHave('userTests')
            ->where('created_at', '<=', $this->guestGraceCutoff());
    }

    private function guestGraceCutoff(): \Illuminate\Support\Carbon
    {
        return now()->subMinutes((int) config('public_trial.retention.legacy_grace_minutes', 30));
    }

    /** @return array<int, object{TABLE_NAME: string, COLUMN_NAME: string}> */
    private function userReferences(): array
    {
        return DB::select("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'users' AND REFERENCED_COLUMN_NAME = 'id'");
    }
}
