<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('logs:clear', function () {
    // Determine the log path
    $logFile = storage_path('logs/laravel.log');

    if (file_exists($logFile)) {
        // Truncate the file
        file_put_contents($logFile, '');
        $this->info('Logs have been cleared successfully.');
    } else {
        $this->warn('Log file does not exist.');
    }
})->purpose('Clear the Laravel log file');
