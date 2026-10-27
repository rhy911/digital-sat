<?php

namespace Tests;

use App\Models\UserTest;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @var array<int, string> */
    private array $attemptControllerTokens = [];

    /**
     * @return array<string, mixed>
     */
    protected function setUpTraits()
    {
        $this->ensureTestingEnvironment();

        return parent::setUpTraits();
    }

    protected function ensureTestingEnvironment(): void
    {
        $env = $this->app->environment();
        $db = (string) config('database.connections.mysql.database');

        if ($env !== 'testing' || $db !== 'sat_app_testing') {
            throw new \RuntimeException(sprintf(
                'Tests must run in APP_ENV=testing against sat_app_testing database! Resolved env: "%s", database: "%s"',
                $env,
                $db
            ));
        }
    }

    protected function withAttemptController(UserTest $attempt): static
    {
        $attempt->refresh();
        $token = $this->attemptControllerTokens[$attempt->id] ??= bin2hex(random_bytes(32));
        if (! $attempt->runsOnChainedClock() && ! $attempt->current_module_paused && ! $attempt->current_module_started_at) {
            $attempt->current_module_started_at = now();
        }
        $attempt->forceFill([
            'attempt_controller_token_hash' => hash('sha256', $token),
            'attempt_controller_last_seen_at' => now(),
        ])->save();

        return $this->withHeader('X-Attempt-Control', $token);
    }
}
