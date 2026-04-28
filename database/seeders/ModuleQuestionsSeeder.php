<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Question;
use Illuminate\Database\Seeder;

class ModuleQuestionsSeeder extends Seeder
{
    /**
     * Link questions to modules in the pivot table.
     */
    public function run(): void
    {
        Module::all()->each(function ($module) {
            $questions = Question::all();

            if ($questions->isEmpty()) {
                return;
            }

            $attach = $questions->pluck('id')
                ->mapWithKeys(fn($id, $index) => [$id => ['position' => $index + 1]]);

            $module->questions()->syncWithoutDetaching($attach->toArray());
        });

        $this->command->info('Module questions linked successfully.');
    }
}
