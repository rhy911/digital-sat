<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
}
