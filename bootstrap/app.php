<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'role' => \App\Http\Middleware\EnsureHasRole::class,
            'teacher.approved' => \App\Http\Middleware\EnsureApprovedTeacher::class,
        ]);

        $middleware->redirectGuestsTo('/signin');
        $middleware->redirectUsersTo(fn (\Illuminate\Http\Request $request) => 
            ($request->user() && $request->hasCookie(\Illuminate\Support\Facades\Auth::getRecallerName()))
                ? '/'
                : '/home'
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('logs:clear')->daily();
        // Soft-deleted content stays recoverable for the configured retention
        // period before this bounded cleanup permanently removes it.
        $schedule->command('recycle-bin:purge')->dailyAt('02:30')->withoutOverlapping(30);
        // Nothing pruned this table before; it grows unbounded.
        $schedule->command('queue:prune-failed --hours=168')->daily();
        // Queue depth / oldest job age. Cheap enough to run during an exam, and
        // its log is what sizes the timing invariants in config/scoring.php.
        // Bounded lock for the same reason as the sweep below: the default is 24h,
        // so one process killed by a host resource limit would take the exam
        // telemetry offline for a day — and the outage would be invisible, because
        // the missing signal IS the monitoring. Two minutes is generous for four
        // COUNTs on a once-a-minute command.
        $schedule->command('sat:queue-health')->everyMinute()->withoutOverlapping(2);
        // Assignment attempts are fixed-time exams, so their clock must keep
        // running after the student closes the tab. Without this the attempt sits
        // in_progress until someone reopens it, one module at a time.
        // 5-minute lock expiry, not the 24h default: this runs unattended on a
        // host where a PHP process can be killed by a resource limit mid-sweep. A
        // default lock outliving a killed process would silently stop every later
        // sweep for a day. Concurrency if a slow sweep outlives the lock is safe —
        // per-module submit locks are what actually guard correctness.
        $schedule->command('assignments:finalize-expired')->everyMinute()->withoutOverlapping(5);
    })
    ->create();
