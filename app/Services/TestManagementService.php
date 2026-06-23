<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TestManagementService
{
    public function __construct(
        private ?TestStructureService $structures = null,
        private ?TestContentCopyService $copies = null,
    ) {
        $this->structures ??= app(TestStructureService::class);
        $this->copies ??= app(TestContentCopyService::class);
    }

    /**
     * Auto-generate full SAT structure safely using transactions.
     */
    public function generateFullSatStructure(string $title, string $testType, ?int $userId = null): Test
    {
        return $this->createConfiguredTestFromBlueprint([
            'title' => $title,
            'test_type' => $testType,
            'status' => 'draft',
            'break_duration_minutes' => $testType === 'module_only' ? 0 : 10,
            'populate_from_pool' => false,
            'modules' => $this->defaultBlueprintModules($testType),
        ], null, $userId);
    }

    public function createConfiguredTestFromBlueprint(array $blueprint, $user = null, ?int $userId = null): Test
    {
        $this->structures->validateBlueprint($blueprint);
        $modules = collect($blueprint['modules'] ?? [])->values();

        if ($modules->isEmpty()) {
            throw ValidationException::withMessages([
                'modules' => 'At least one module row is required.',
            ]);
        }

        return DB::transaction(function () use ($blueprint, $modules, $user, $userId) {
            $test = Test::create([
                'title' => $blueprint['title'],
                'test_type' => $blueprint['test_type'] ?? 'custom_test',
                'break_duration_minutes' => (int) ($blueprint['break_duration_minutes'] ?? 0),
                'status' => $blueprint['status'] ?? 'draft',
                'created_by' => $userId,
                'is_public' => false,
            ]);

            $sections = [];
            $usedQuestionIds = collect();

            foreach ($modules as $index => $moduleData) {
                $sectionType = $moduleData['section_type'];
                if (! isset($sections[$sectionType])) {
                    $sections[$sectionType] = Section::create([
                        'test_id' => $test->id,
                        'type' => $sectionType,
                        'name' => $sectionType === Section::TYPE_RW ? 'Reading and Writing' : 'Math',
                        'order' => $sectionType === Section::TYPE_RW ? 1 : 2,
                        'created_by' => $userId,
                        'is_public' => false,
                    ]);
                }

                $section = $sections[$sectionType];
                $module = $this->createStandardModuleForSection(
                    $section,
                    (int) $moduleData['module_number'],
                    $moduleData['difficulty_level'],
                    (int) $moduleData['duration_minutes'],
                    (int) $moduleData['total_questions'],
                    $userId,
                    $index + 1
                );

                if (! empty($blueprint['populate_from_pool'])) {
                    $questions = $this->selectQuestionsForModule(
                        $sectionType,
                        (int) $moduleData['total_questions'],
                        $usedQuestionIds->all(),
                        $user
                    );

                    if ($questions->count() < (int) $moduleData['total_questions']) {
                        throw ValidationException::withMessages([
                            "modules.{$index}.total_questions" => sprintf(
                                'Not enough complete %s questions in the pool. Needed %d, found %d.',
                                $sectionType === Section::TYPE_RW ? 'Reading & Writing' : 'Math',
                                (int) $moduleData['total_questions'],
                                $questions->count()
                            ),
                        ]);
                    }

                    foreach ($questions->values() as $position => $question) {
                        $module->questions()->attach($question->id, ['position' => $position + 1]);
                        $usedQuestionIds->push($question->id);
                    }
                }
            }

            $test->refreshTotalDuration();

            if (($blueprint['status'] ?? 'draft') === 'active') {
                $this->structures->validateForPublication($test->fresh());
            }

            return $test->load('sections.modules.questions');
        });
    }

    private function defaultBlueprintModules(string $testType): array
    {
        if ($testType === 'module_only') {
            return [[
                'section_type' => Section::TYPE_RW,
                'module_number' => 1,
                'difficulty_level' => Module::DIFFICULTY_STANDARD,
                'duration_minutes' => Module::RW_DURATION,
                'total_questions' => Module::RW_QUESTIONS,
            ]];
        }

        if ($testType === 'short_test') {
            return [
                [
                    'section_type' => Section::TYPE_RW,
                    'module_number' => 1,
                    'difficulty_level' => Module::DIFFICULTY_STANDARD,
                    'duration_minutes' => 20,
                    'total_questions' => 15,
                ],
                [
                    'section_type' => Section::TYPE_MATH,
                    'module_number' => 1,
                    'difficulty_level' => Module::DIFFICULTY_STANDARD,
                    'duration_minutes' => 20,
                    'total_questions' => 12,
                ],
            ];
        }

        if ($testType === Test::TYPE_FULL) {
            return [
                ['section_type' => Section::TYPE_RW, 'module_number' => 1, 'difficulty_level' => Module::DIFFICULTY_STANDARD, 'duration_minutes' => Module::RW_DURATION, 'total_questions' => Module::RW_QUESTIONS],
                ['section_type' => Section::TYPE_RW, 'module_number' => 2, 'difficulty_level' => Module::DIFFICULTY_STANDARD, 'duration_minutes' => Module::RW_DURATION, 'total_questions' => Module::RW_QUESTIONS],
                ['section_type' => Section::TYPE_MATH, 'module_number' => 1, 'difficulty_level' => Module::DIFFICULTY_STANDARD, 'duration_minutes' => Module::MATH_DURATION, 'total_questions' => Module::MATH_QUESTIONS],
                ['section_type' => Section::TYPE_MATH, 'module_number' => 2, 'difficulty_level' => Module::DIFFICULTY_STANDARD, 'duration_minutes' => Module::MATH_DURATION, 'total_questions' => Module::MATH_QUESTIONS],
            ];
        }

        return [
            ['section_type' => Section::TYPE_RW, 'module_number' => 1, 'difficulty_level' => Module::DIFFICULTY_STANDARD, 'duration_minutes' => Module::RW_DURATION, 'total_questions' => Module::RW_QUESTIONS],
            ['section_type' => Section::TYPE_RW, 'module_number' => 2, 'difficulty_level' => Module::DIFFICULTY_EASY, 'duration_minutes' => Module::RW_DURATION, 'total_questions' => Module::RW_QUESTIONS],
            ['section_type' => Section::TYPE_RW, 'module_number' => 2, 'difficulty_level' => Module::DIFFICULTY_HARD, 'duration_minutes' => Module::RW_DURATION, 'total_questions' => Module::RW_QUESTIONS],
            ['section_type' => Section::TYPE_MATH, 'module_number' => 1, 'difficulty_level' => Module::DIFFICULTY_STANDARD, 'duration_minutes' => Module::MATH_DURATION, 'total_questions' => Module::MATH_QUESTIONS],
            ['section_type' => Section::TYPE_MATH, 'module_number' => 2, 'difficulty_level' => Module::DIFFICULTY_EASY, 'duration_minutes' => Module::MATH_DURATION, 'total_questions' => Module::MATH_QUESTIONS],
            ['section_type' => Section::TYPE_MATH, 'module_number' => 2, 'difficulty_level' => Module::DIFFICULTY_HARD, 'duration_minutes' => Module::MATH_DURATION, 'total_questions' => Module::MATH_QUESTIONS],
        ];
    }

    private function selectQuestionsForModule(string $sectionType, int $limit, array $excludedIds, $user)
    {
        return Question::visibleTo($user)
            ->where('section_type', $sectionType)
            ->where('is_complete', true)
            ->when(! empty($excludedIds), fn ($query) => $query->whereNotIn('id', $excludedIds))
            ->orderBy('is_pretest')
            ->orderBy('difficulty')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Create standard module helper.
     */
    private function createStandardModuleForSection(Section $section, int $moduleNumber, string $difficultyLevel, int $duration, int $totalQuestions, ?int $userId = null, ?int $order = null): Module
    {
        $uniqueKey = strtoupper(substr($section->type, 0, 2)).'_M'.$moduleNumber.'_'.strtoupper($difficultyLevel).'_'.strtoupper(Str::random(6));
        $order ??= ($moduleNumber === 1) ? 1 : (($difficultyLevel === Module::DIFFICULTY_EASY) ? 2 : 3);

        $module = Module::create([
            'section_id' => $section->id,
            'module_number' => $moduleNumber,
            'difficulty_level' => $difficultyLevel,
            'duration_minutes' => $duration,
            'total_questions' => $totalQuestions,
            'key' => $uniqueKey,
            'order' => $order,
            'created_by' => $userId,
            'is_public' => false,
        ]);

        $module->sections()->syncWithoutDetaching([$section->id]);

        return $module;
    }

    /**
     * Clone a Test (Hierarchy only).
     */
    public function cloneTest(int $id, ?int $userId = null): Test
    {
        return $this->copies->copyTest(Test::findOrFail($id), $userId);
    }

    /**
     * Clone a Module (Hierarchy only).
     */
    public function cloneModule(int $id, ?int $sectionId = null, ?int $userId = null): Module
    {
        if ($sectionId) {
            app(TestContentLockService::class)->ensureUnlocked(Section::findOrFail($sectionId)->test);
        }

        return $this->copies->copyModule(Module::findOrFail($id), $sectionId ? Section::findOrFail($sectionId) : null, $userId);
    }

    /**
     * Delete Test (with optional cascading deletion of children).
     */
    public function deleteTest(int $id, bool $deleteChildren): void
    {
        $test = Test::with('sections.modules.questions')->findOrFail($id);
        app(TestContentLockService::class)->ensureUnlocked($test);

        if (DB::table('user_tests')->where('test_id', $test->id)->exists()) {
            throw ValidationException::withMessages(['test' => 'Cannot delete test with existing student attempts.']);
        }

        DB::transaction(function () use ($test, $deleteChildren) {
            if ($deleteChildren) {
                foreach ($test->sections as $section) {
                    foreach ($section->modules as $module) {
                        foreach ($module->questions as $question) {
                            $question->delete();
                        }
                        $module->delete();
                    }
                    $section->delete();
                }
            }
            $test->delete();
        });
    }

    /**
     * Delete Section (with optional cascading deletion of children).
     */
    public function deleteSection(int $id, bool $deleteChildren): void
    {
        $section = Section::with(['test', 'modules.questions'])->findOrFail($id);
        $test = $section->test;
        app(TestContentLockService::class)->ensureUnlocked($test);

        if ($test && DB::table('user_tests')->where('test_id', $test->id)->exists()) {
            throw ValidationException::withMessages(['section' => 'Cannot delete section of a test with existing student attempts.']);
        }

        DB::transaction(function () use ($section, $deleteChildren) {
            if ($deleteChildren) {
                foreach ($section->modules as $module) {
                    foreach ($module->questions as $question) {
                        $question->delete();
                    }
                    $module->delete();
                }
            }
            $section->delete();
        });

        if ($test) {
            $test->refreshTotalDuration();
        }
    }

    /**
     * Delete Module (with optional cascading deletion of children).
     */
    public function deleteModule(int $id, bool $deleteChildren): void
    {
        $module = Module::with(['section.test', 'questions'])->findOrFail($id);
        $test = $module->section->test ?? null;
        app(TestContentLockService::class)->ensureModuleUnlocked($module);

        if ($test && DB::table('user_tests')->where('test_id', $test->id)->exists()) {
            throw ValidationException::withMessages(['module' => 'Cannot delete module of a test with existing student attempts.']);
        }

        DB::transaction(function () use ($module, $deleteChildren) {
            if ($deleteChildren) {
                foreach ($module->questions as $question) {
                    $question->delete();
                }
            }
            $module->delete();
        });

        if ($test) {
            $test->refreshTotalDuration();
        }
    }
}
