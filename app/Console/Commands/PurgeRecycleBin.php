<?php

namespace App\Console\Commands;

use App\Services\RecycleBinService;
use Illuminate\Console\Command;

class PurgeRecycleBin extends Command
{
    protected $signature = 'recycle-bin:purge';

    protected $description = 'Permanently delete soft-deleted content older than the recycle-bin retention period';

    public function handle(RecycleBinService $recycleBin): int
    {
        $result = $recycleBin->purgeExpired();

        $this->info(sprintf(
            'Purged %d recycle-bin item(s); skipped %d item(s) still referenced or blocked.',
            $result['purged'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
