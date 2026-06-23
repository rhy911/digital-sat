<?php

namespace App\Jobs;

use App\Models\Module;
use App\Models\UserTest;
use App\Services\TestProgressionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScoreModuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userTestId;
    public $moduleId;
    public $sectionId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userTestId, int $moduleId, int $sectionId)
    {
        $this->userTestId = $userTestId;
        $this->moduleId = $moduleId;
        $this->sectionId = $sectionId;
    }

    /**
     * Execute the job.
     */
    public function handle(TestProgressionService $progression): void
    {
        try {
            Log::info("ScoreModuleJob started", [
                'user_test_id' => $this->userTestId,
                'module_id' => $this->moduleId
            ]);

            $userTest = UserTest::findOrFail($this->userTestId);
            $module = Module::findOrFail($this->moduleId);
            $result = $progression->submit($userTest, $module);
            Cache::put("scoring_result_{$userTest->id}", $result, 300);
        } catch (\Throwable $e) {
            Log::error("EXCEPTION in ScoreModuleJob", ['exception' => $e]);
            $result = [
                'status' => 'error',
                'error' => 'Server error during submission.',
                'message' => 'An unexpected server error occurred.'
            ];
            Cache::put("scoring_result_{$this->userTestId}", $result, 300);
        }
    }

}
