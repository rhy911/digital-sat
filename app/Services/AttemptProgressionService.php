<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Test;
use App\Models\UserTest;
use Carbon\CarbonInterface;

/**
 * Backward compatibility adapter delegating to AttemptEngine.
 */
class AttemptProgressionService
{
    public function __construct(
        private ?AttemptEngine $engine = null
    ) {
        $this->engine ??= app(AttemptEngine::class);
    }

    public function firstModule(Test $test, ?UserTest $attempt = null): ?Module
    {
        return $this->engine->firstModule($test, $attempt);
    }

    public function issueInitialModule(UserTest $attempt, Test $test): Module
    {
        return $this->engine->issueInitialModule($attempt, $test);
    }

    public function assertIssued(UserTest $attempt, Module $module): void
    {
        $this->engine->assertIssued($attempt, $module);
    }

    public function advance(UserTest $attempt, array $result, ?CarbonInterface $startNextAt = null): ?Module
    {
        return $this->engine->advance($attempt, $result, $startNextAt);
    }
}
