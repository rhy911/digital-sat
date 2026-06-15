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
        ]);

        $middleware->redirectGuestsTo('/signin');
        $middleware->redirectUsersTo(fn (\Illuminate\Http\Request $request) => 
            ($request->user() && $request->hasCookie(\Illuminate\Support\Facades\Auth::getRecallerName()))
                ? '/'
                : '/student/progress'
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('logs:clear')->daily();
    })
    ->create();
