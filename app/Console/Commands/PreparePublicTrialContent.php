<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Test;
use App\Services\TestManagementService;
use App\Services\TestStructureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreparePublicTrialContent extends Command
{
    protected $signature = 'public-trial:prepare-content';

    protected $description = 'Derive and validate the configured public-trial modules from the selected source test.';

    public function handle(TestManagementService $management, TestStructureService $structures): int
    {
        $sourceTest = Test::where('ulid', config('public_trial.source_test_ulid'))
            ->where('status', 'active')->first();
        if (! $sourceTest) {
            $this->error('The configured active source test was not found.');

            return self::FAILURE;
        }

        $sourceTest->load('sections.modules');
        $adminId = DB::table('users')->where('role', 'admin')->value('id');
        if (! $adminId) {
            $this->error('Create an administrator account before preparing trial content.');

            return self::FAILURE;
        }

        foreach ([
            'reading_writing' => ['01KW8MCQY5ZR61M7WEY42JRR8A', 'DFET SAT Trial — Reading and Writing'],
            'math' => ['01KW8MCQYF2XTYJSSPH745KS0R', 'DFET SAT Trial — Math'],
        ] as $key => [$sourceModuleUlid, $title]) {
            $targetUlid = config("public_trial.tests.{$key}");
            $target = Test::where('ulid', $targetUlid)->first();
            if (! $target) {
                $module = Module::where('ulid', $sourceModuleUlid)
                    ->whereHas('section', fn ($query) => $query->where('test_id', $sourceTest->id))
                    ->with('section')
                    ->first();
                if (! $module || ! $module->section) {
                    $this->error("The configured {$key} source module was not found in the active source test.");

                    return self::FAILURE;
                }
                $target = $management->deriveFromModule($module, $module->section, $title, (int) $adminId);
                $target->forceFill(['ulid' => $targetUlid])->save();
            }

            $structures->validateForPublication($target->fresh());
            $this->info("{$key}: {$target->ulid} validated as a draft. Review and publish it in the Test Builder.");
        }

        $structures->validateForPublication($sourceTest);
        $this->info('Adaptive full practice test: '.$sourceTest->ulid.' ready. Review the question content before enabling it.');

        return self::SUCCESS;
    }
}
