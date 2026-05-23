<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestManagementService
{
    /**
     * Auto-generate full SAT structure safely using transactions.
     */
    public function generateFullSatStructure(string $title, string $testType): Test
    {
        return DB::transaction(function () use ($title, $testType) {
            $test = Test::create([
                'title' => $title,
                'test_type' => $testType,
                'break_duration_minutes' => ($testType === 'module_only' ? 0 : 10),
                'status' => 'draft',
            ]);

            $isShort = ($testType === 'short_test');
            $isModuleOnly = ($testType === 'module_only');

            $rwDuration = $isShort ? 20 : 32;
            $rwQuestions = $isShort ? 15 : 27;
            $mathDuration = $isShort ? 20 : 35;
            $mathQuestions = $isShort ? 12 : 22;

            if ($isModuleOnly) {
                $section = Section::create([
                    'test_id' => $test->id,
                    'type' => Section::TYPE_RW,
                    'name' => 'Focused Module',
                    'order' => 1,
                ]);
                $this->createStandardModuleForSection($section, 1, Module::DIFFICULTY_STANDARD, 32, 27);
            } else {
                // Create R&W Section
                $rwSection = Section::create([
                    'test_id' => $test->id,
                    'type' => Section::TYPE_RW,
                    'name' => 'Reading and Writing',
                    'order' => 1,
                ]);

                // Create R&W Modules
                $this->createStandardModuleForSection($rwSection, 1, Module::DIFFICULTY_STANDARD, $rwDuration, $rwQuestions);
                $this->createStandardModuleForSection($rwSection, 2, Module::DIFFICULTY_EASY, $rwDuration, $rwQuestions);
                $this->createStandardModuleForSection($rwSection, 2, Module::DIFFICULTY_HARD, $rwDuration, $rwQuestions);

                // Create Math Section
                $mathSection = Section::create([
                    'test_id' => $test->id,
                    'type' => Section::TYPE_MATH,
                    'name' => 'Math',
                    'order' => 2,
                ]);

                // Create Math Modules
                $this->createStandardModuleForSection($mathSection, 1, Module::DIFFICULTY_STANDARD, $mathDuration, $mathQuestions);
                $this->createStandardModuleForSection($mathSection, 2, Module::DIFFICULTY_EASY, $mathDuration, $mathQuestions);
                $this->createStandardModuleForSection($mathSection, 2, Module::DIFFICULTY_HARD, $mathDuration, $mathQuestions);
            }
            
            $test->refreshTotalDuration();

            return $test->load('sections.modules');
        });
    }

    /**
     * Create standard module helper.
     */
    private function createStandardModuleForSection(Section $section, int $moduleNumber, string $difficultyLevel, int $duration, int $totalQuestions): Module
    {
        $uniqueKey = strtoupper(substr($section->type, 0, 2)) . '_M' . $moduleNumber . '_' . strtoupper($difficultyLevel) . '_' . strtoupper(Str::random(6));
        $order = ($moduleNumber === 1) ? 1 : (($difficultyLevel === Module::DIFFICULTY_EASY) ? 2 : 3);
        
        $module = Module::create([
            'module_number' => $moduleNumber,
            'difficulty_level' => $difficultyLevel,
            'duration_minutes' => $duration,
            'total_questions' => $totalQuestions,
            'key' => $uniqueKey,
            'order' => $order,
        ]);
        
        $module->sections()->attach($section->id);
        return $module;
    }

    /**
     * Clone a Test (Hierarchy only).
     */
    public function cloneTest(int $id): Test
    {
        return DB::transaction(function () use ($id) {
            $originalTest = Test::with('sections.modules')->findOrFail($id);
            
            $clonedTest = $originalTest->replicate();
            $clonedTest->title = $originalTest->title . ' (Clone)';
            $clonedTest->status = 'draft';
            $clonedTest->save();

            foreach ($originalTest->sections as $section) {
                $clonedSection = $section->replicate();
                $clonedSection->test_id = $clonedTest->id;
                $clonedSection->save();

                foreach ($section->modules as $module) {
                    $clonedModule = $module->replicate();
                    $clonedModule->key = $module->key . '_CLONE_' . strtoupper(Str::random(4));
                    $clonedModule->save();

                    // Attach to the new section
                    $clonedModule->sections()->attach($clonedSection->id);
                }
            }
            
            $clonedTest->refreshTotalDuration();
            return $clonedTest->load('sections.modules');
        });
    }

    /**
     * Clone a Module (Hierarchy only).
     */
    public function cloneModule(int $id, ?int $sectionId = null): Module
    {
        return DB::transaction(function () use ($id, $sectionId) {
            $originalModule = Module::findOrFail($id);
            
            $clonedModule = $originalModule->replicate();
            $clonedModule->key = $originalModule->key . '_CLONE_' . strtoupper(Str::random(4));
            $clonedModule->save();

            if ($sectionId) {
                $clonedModule->sections()->attach($sectionId);
            }

            return $clonedModule;
        });
    }

    /**
     * Delete Test (with optional cascading deletion of children).
     */
    public function deleteTest(int $id, bool $deleteChildren): void
    {
        $test = Test::with('sections.modules.questions')->findOrFail($id);
        
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
