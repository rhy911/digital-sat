<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Build a cohort of students all parked on the same module, ready to submit in
 * the same second.
 *
 * The 70-student incident only reproduces as a synchronized burst — a ramped
 * load test does not create it. Pair this with a driver (k6, or
 * `xargs -P70 curl`) that logs each student in and then waits on a shared
 * barrier before POSTing /engine/test/submit-module.
 */
class SeedStressCohort extends Command
{
    protected $signature = 'sat:seed-stress-cohort
        {--students=70 : How many students to create}
        {--test= : Test id or ulid to seed against}
        {--position=last : Which module to park on — first|last}
        {--password=stress-test-password : Shared password for every seeded student}
        {--prefix=stress : Email prefix, so the cohort is easy to find and delete}
        {--cleanup : Delete a previously seeded cohort and all its attempts, then exit}';

    protected $description = 'Seed N students parked on one module for a concurrent-submit load test (local/staging only).';

    public function handle(): int
    {
        // Creates fake users and attempts. Never on production.
        if (! app()->environment(['local', 'staging'])) {
            $this->error('Refusing to run outside local/staging. Current environment: '.app()->environment());

            return self::FAILURE;
        }

        if ($this->option('cleanup')) {
            return $this->cleanup((string) $this->option('prefix'));
        }

        $test = $this->resolveTest();
        if (! $test) {
            return self::FAILURE;
        }

        $target = $this->resolveTargetModule($test);
        if (! $target) {
            $this->error('Could not resolve a target module for that test.');

            return self::FAILURE;
        }

        $priorModules = $this->modulesBefore($test, $target);
        $count = max(1, (int) $this->option('students'));
        $password = (string) $this->option('password');
        $prefix = (string) $this->option('prefix');

        $this->info("Test: {$test->title} (id {$test->id})");
        $this->info("Target module: id {$target->id}, module_number {$target->module_number}, difficulty {$target->difficulty_level}");
        $this->info('Prior modules to backfill: '.($priorModules->pluck('id')->implode(', ') ?: '(none)'));

        $rows = [];
        $progress = $this->output->createProgressBar($count);
        $progress->start();

        for ($i = 1; $i <= $count; $i++) {
            $email = "{$prefix}{$i}@example.test";

            $student = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => "Stress Student {$i}",
                    'username' => "{$prefix}{$i}",
                    'password' => Hash::make($password),
                ]
            );

            // `role` and `email_verified_at` are NOT in User::$fillable, so passing
            // them above is silently dropped — the students then land on the email
            // verification wall instead of the engine, and every request looks like
            // a 200 that never settles.
            $student->forceFill([
                'role' => 'student',
                'email_verified_at' => now(),
            ])->save();

            // One active attempt per student; drop any leftovers from a prior run.
            UserTest::where('user_id', $student->id)
                ->where('test_id', $test->id)
                ->whereNull('assignment_id')
                ->where('status', 'in_progress')
                ->update(['status' => 'abandoned']);

            $attempt = UserTest::create([
                'user_id' => $student->id,
                'test_id' => $test->id,
                'status' => 'in_progress',
                'current_module_id' => $target->id,
                'current_module_started_at' => now(),
                'current_module_elapsed_seconds' => 0,
            ]);

            // An adaptive terminal submit re-scores module 2 through the recorded
            // path, so the attempt has to remember which branch it took. Only
            // easy/hard are valid values — a linear form's module 2 is 'standard'
            // and writing that truncates the enum column.
            $branch = $target->difficulty_level;
            if ((int) $target->module_number === 2
                && in_array($branch, [Module::DIFFICULTY_EASY, Module::DIFFICULTY_HARD], true)) {
                $section = $target->section()->first();
                $field = $section?->type === \App\Models\Section::TYPE_MATH ? 'math_m2_path' : 'rw_m2_path';
                $attempt->forceFill([$field => $branch])->save();
            }

            // completeResponsesForModule() throws unless EVERY presented question of
            // a scored module has a response row. Without this backfill the finalize
            // path never executes and the load test measures the wrong thing.
            foreach ($priorModules as $module) {
                $this->backfillAnswers($attempt, $module);
            }

            $rows[] = [$i, $email, $attempt->ulid, $target->ulid];
            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);

        $this->table(['#', 'email', 'attempt_ulid', 'module_ulid'], array_slice($rows, 0, 5));
        $this->info(count($rows).' students seeded. Password for all: '.$password);
        $this->newLine();
        $this->line('Session URL pattern: /engine/session/{module_ulid}?attempt={attempt_ulid}');
        $this->line('Have every client wait on a shared barrier, then POST /engine/test/submit-module in the same second.');
        $this->line('Watch it with: php artisan sat:queue-health');

        return self::SUCCESS;
    }

    /**
     * Remove a seeded cohort. Answers and module submissions go with the attempts
     * via the schema's cascade; the users themselves are only ever the fake
     * `<prefix>N@example.test` accounts this command created.
     */
    private function cleanup(string $prefix): int
    {
        $students = User::where('email', 'like', $prefix.'%@example.test')->get();

        if ($students->isEmpty()) {
            $this->info("No seeded students found with prefix '{$prefix}'.");

            return self::SUCCESS;
        }

        $attemptIds = UserTest::whereIn('user_id', $students->pluck('id'))->pluck('id');

        UserTestAnswer::whereIn('user_test_id', $attemptIds)->delete();
        \App\Models\UserTestModuleSubmission::whereIn('user_test_id', $attemptIds)->delete();
        UserTest::whereIn('id', $attemptIds)->forceDelete();
        User::whereIn('id', $students->pluck('id'))->forceDelete();

        $this->info("Removed {$students->count()} students and {$attemptIds->count()} attempts (prefix '{$prefix}').");

        return self::SUCCESS;
    }

    private function resolveTest(): ?Test
    {
        $key = $this->option('test');

        if (! $key) {
            $test = Test::where('status', 'active')->latest('id')->first();
            if (! $test) {
                $this->error('No active test found. Pass --test=<id|ulid>.');
            }

            return $test;
        }

        $test = Test::where('ulid', $key)->orWhere('id', $key)->first();
        if (! $test) {
            $this->error("No test matches --test={$key}");
        }

        return $test;
    }

    private function resolveTargetModule(Test $test): ?Module
    {
        $modules = $this->orderedModules($test);

        return $this->option('position') === 'first' ? $modules->first() : $modules->last();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Module>
     */
    private function orderedModules(Test $test): \Illuminate\Support\Collection
    {
        return $test->sections()
            ->with('modules.questions')
            ->orderBy('order')
            ->get()
            ->flatMap(fn ($section) => $section->modules
                ->sortBy([['module_number', 'asc'], ['order', 'asc'], ['id', 'asc']])
                ->values());
    }

    /**
     * @return \Illuminate\Support\Collection<int, Module>
     */
    private function modulesBefore(Test $test, Module $target): \Illuminate\Support\Collection
    {
        $modules = $this->orderedModules($test);
        $index = $modules->search(fn ($module) => (int) $module->id === (int) $target->id);

        if ($index === false || $index === 0) {
            return collect();
        }

        // Only one module-2 branch is ever presented, so skip the branch the target
        // attempt did not take.
        return $modules->slice(0, $index)->values()->filter(function ($module) use ($target) {
            if ((int) $module->module_number !== 2 || ! $module->difficulty_level) {
                return true;
            }

            return $module->section_id !== $target->section_id
                || $module->difficulty_level === $target->difficulty_level;
        })->values();
    }

    private function backfillAnswers(UserTest $attempt, Module $module): void
    {
        $questions = $module->questions()->with('answerChoices')->get();
        $rows = [];

        foreach ($questions as $question) {
            $correct = $question->answerChoices->firstWhere('is_correct', true);

            $rows[] = [
                'user_test_id' => $attempt->id,
                'module_id' => $module->id,
                'question_id' => $question->id,
                'selected_answer' => $correct->label ?? 'A',
                'is_correct' => true,
                'time_spent' => 30,
                // The snapshot is what SatScoringService actually scores against —
                // see UserTestAnswer::getQuestionAttribute(). IRT params must be here
                // or the seeded attempt scores differently from a real one.
                'question_snapshot' => json_encode([
                    'stem' => $question->stem,
                    'question_type' => $question->question_type,
                    'difficulty' => $question->difficulty,
                    'is_pretest' => (bool) $question->is_pretest,
                    'skill_domain' => $question->skill_domain,
                    'irt_a' => (float) $question->irt_a,
                    'irt_b' => (float) $question->irt_b,
                    'irt_c' => (float) $question->irt_c,
                    'section_type' => $question->section_type,
                    'choices' => $question->answerChoices->map(fn ($choice) => [
                        'label' => $choice->label,
                        'content' => $choice->content,
                        'is_correct' => (bool) $choice->is_correct,
                        'order' => $choice->order,
                    ])->toArray(),
                    'spr_answers' => [],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows) {
            UserTestAnswer::upsert(
                $rows,
                ['user_test_id', 'module_id', 'question_id'],
                ['selected_answer', 'is_correct', 'time_spent', 'question_snapshot', 'updated_at']
            );
        }
    }
}
