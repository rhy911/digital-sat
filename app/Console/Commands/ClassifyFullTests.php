<?php

namespace App\Console\Commands;

use App\Models\Test;
use Illuminate\Console\Command;

class ClassifyFullTests extends Command
{
    protected $signature = 'sat:classify-full-tests {--apply : Apply adaptive classifications and return unsupported forms to draft}';

    protected $description = 'Report structural classification for existing full-length tests';

    public function handle(): int
    {
        $rows = [];
        Test::with('sections.modules')->whereIn('test_type', [Test::TYPE_FULL, Test::TYPE_ADAPTIVE_FULL])->orderBy('id')->each(function ($test) use (&$rows) {
            $adaptive = $test->sections->count() === 2 && $test->sections->every(fn ($section) => $section->modules->where('module_number', 1)->count() === 1
                && $section->modules->where('module_number', 2)->pluck('difficulty_level')->sort()->values()->all() === ['easy', 'hard']);
            $normal = $test->sections->count() === 2 && $test->sections->every(fn ($section) => $section->modules->where('module_number', 1)->count() === 1
                && $section->modules->where('module_number', 2)->count() === 1);
            $classification = $adaptive ? Test::TYPE_ADAPTIVE_FULL : ($normal ? Test::TYPE_FULL : 'unsupported');
            $rows[] = [$test->id, $test->title, $test->test_type, $classification];
            if ($this->option('apply')) {
                $test->update($classification === 'unsupported' ? ['status' => 'draft'] : ['test_type' => $classification]);
            }
        });
        $this->table(['ID', 'Test', 'Current', 'Classification'], $rows);
        $this->info($this->option('apply') ? 'Classification applied.' : 'Dry run only.');

        return self::SUCCESS;
    }
}
