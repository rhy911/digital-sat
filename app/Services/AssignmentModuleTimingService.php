<?php

namespace App\Services;

use App\Models\Module;
use App\Models\UserTest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Backward compatibility adapter delegating to ExamAttemptEngine.
 */
class AssignmentModuleTimingService
{
    public function __construct(
        private ?ExamAttemptEngine $engine = null
    ) {
        $this->engine ??= app(ExamAttemptEngine::class);
    }

    public function durationSeconds(Module $module): int
    {
        return $this->engine->durationSeconds($module);
    }

    public function timing(UserTest $userTest, Module $module, ?Carbon $now = null): array
    {
        return $this->engine->timing($userTest, $module, $now);
    }

    public function deadline(UserTest $userTest, Module $module): ?CarbonInterface
    {
        return $this->engine->deadline($userTest, $module);
    }

    public function syncElapsed(UserTest $userTest, Module $module): array
    {
        return $this->engine->syncElapsed($userTest, $module);
    }
}
