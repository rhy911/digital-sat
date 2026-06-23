<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TestStructureService
{
    public const FLOW_ADAPTIVE = 'adaptive';

    public const FLOW_LINEAR = 'linear';

    public const FLOW_SINGLE = 'single';

    public function validate(Test $test, bool $requireQuestions = true): array
    {
        $test->load('sections.modules.questions');
        $sections = $test->sections->sortBy('order')->values();

        if ($sections->isEmpty()) {
            $this->fail('Test must contain at least one section.');
        }

        $flows = $sections->mapWithKeys(fn (Section $section) => [
            $section->id => $this->classifySection($section, $test->test_type),
        ]);

        match ($test->test_type) {
            'full_length' => $this->validateFullLength($sections, $flows),
            'adaptive_full_length' => $this->validateAdaptiveFullLength($sections, $flows),
            'short_test' => $this->validateShortTest($sections, $flows),
            'module_only' => $this->validateModuleOnly($sections),
            'section_only' => $this->validateSectionOnly($sections),
            'custom_test' => $this->validateCustom($sections, $flows),
            default => $this->fail('Unsupported test type.'),
        };

        if ($requireQuestions) {
            foreach ($sections as $section) {
                foreach ($section->modules as $module) {
                    if ($module->questions->isEmpty()) {
                        $this->fail("{$section->name}, Module {$module->module_number} must contain at least one question.");
                    }
                }
            }
        }

        return ['sections' => $sections, 'flows' => $flows];
    }

    public function validateForPublication(Test $test): array
    {
        $shape = $this->validate($test);
        if (! in_array($test->test_type, [Test::TYPE_FULL, Test::TYPE_ADAPTIVE_FULL], true)) {
            return $shape;
        }

        foreach ($shape['sections'] as $section) {
            $expectedCount = $section->type === Section::TYPE_RW ? Module::RW_QUESTIONS : Module::MATH_QUESTIONS;
            $expectedDuration = $section->type === Section::TYPE_RW ? Module::RW_DURATION : Module::MATH_DURATION;
            foreach ($section->modules as $module) {
                if ($module->questions->count() !== $expectedCount || (int) $module->total_questions !== $expectedCount) {
                    $this->fail("{$section->name}, Module {$module->module_number} requires exactly {$expectedCount} presented questions.");
                }
                if ((int) $module->duration_minutes !== $expectedDuration) {
                    $this->fail("{$section->name}, Module {$module->module_number} requires {$expectedDuration} minutes.");
                }
            }

            if ($test->test_type === Test::TYPE_ADAPTIVE_FULL) {
                $this->validateAdaptiveMeasurement($section);
            }
        }

        return $shape;
    }

    public function validateBlueprint(array $blueprint): void
    {
        $rows = collect($blueprint['modules'] ?? [])->map(fn (array $row, int $index) => (object) [
            'section_type' => $row['section_type'],
            'module_number' => (int) $row['module_number'],
            'difficulty_level' => $row['difficulty_level'],
            'order' => $index + 1,
        ]);

        if ($rows->isEmpty()) {
            $this->fail('At least one module is required.');
        }

        $groups = $rows->groupBy('section_type');
        $type = $blueprint['test_type'] ?? 'custom_test';

        if ($type === 'module_only') {
            if ($rows->count() !== 1) {
                $this->fail('Module-only tests require exactly one module.');
            }

            return;
        }

        if ($type === 'short_test') {
            if ($groups->keys()->sort()->values()->all() !== collect([Section::TYPE_MATH, Section::TYPE_RW])->sort()->values()->all()
                || $groups->contains(fn ($modules) => $modules->count() !== 1 || $modules->first()->difficulty_level !== Module::DIFFICULTY_STANDARD)) {
                $this->fail('Short tests require one standard Reading & Writing module and one standard Math module.');
            }

            return;
        }

        if ($type === 'full_length') {
            if ($groups->count() !== 2 || $groups->contains(fn ($modules) => ! $this->isNormalFullRows($modules))) {
                $this->fail('Normal full tests require one fixed Module 1 and Module 2 in both sections.');
            }

            return;
        }

        if ($type === 'adaptive_full_length') {
            if ($groups->count() !== 2 || $groups->contains(fn ($modules) => ! $this->isStrictAdaptiveRows($modules))) {
                $this->fail('Adaptive full tests require standard Module 1 plus easy and hard Module 2 branches in both sections.');
            }

            return;
        }

        if ($type === 'section_only') {
            if ($groups->count() !== 1 || ! ($this->isAdaptiveRows($rows) || $this->isLinearRows($rows))) {
                $this->fail('Section-only tests require one adaptive section or one linear sequence of standard modules.');
            }

            return;
        }

        if ($type === 'custom_test' && ($groups->count() > 2 || $groups->contains(fn ($modules) => ! $this->isLinearRows($modules)))) {
            $this->fail('Custom tests require contiguous standard modules in each section.');
        }
    }

    public function classifySection(Section $section, string $testType): string
    {
        $modules = $section->modules->sortBy([['module_number', 'asc'], ['order', 'asc']])->values();
        if ($testType === 'module_only' && $modules->count() === 1) {
            return self::FLOW_SINGLE;
        }
        if ($testType === 'full_length' && $this->isNormalFullRows($modules)) {
            return self::FLOW_LINEAR;
        }
        if ($testType === 'adaptive_full_length' && $this->isStrictAdaptiveRows($modules)) {
            return self::FLOW_ADAPTIVE;
        }
        if (in_array($testType, ['full_length', 'adaptive_full_length'], true)) {
            $this->fail("{$section->name} does not match the selected full-test type.");
        }
        if ($this->isAdaptiveRows($modules)) {
            return self::FLOW_ADAPTIVE;
        }
        if ($this->isLinearRows($modules)) {
            return self::FLOW_LINEAR;
        }

        $this->fail("{$section->name} has an unsupported or ambiguous module structure.");
    }

    public function orderedModules(Section $section): Collection
    {
        return $section->modules->sortBy([['module_number', 'asc'], ['order', 'asc'], ['id', 'asc']])->values();
    }

    private function validateFullLength(Collection $sections, Collection $flows): void
    {
        $types = $sections->pluck('type')->sort()->values()->all();
        $expected = collect([Section::TYPE_RW, Section::TYPE_MATH])->sort()->values()->all();
        if ($types !== $expected || $sections->count() !== 2 || $flows->contains(fn ($flow) => $flow !== self::FLOW_LINEAR)) {
            $this->fail('Normal full tests require fixed Reading & Writing and Math sections.');
        }
    }

    private function validateAdaptiveFullLength(Collection $sections, Collection $flows): void
    {
        $types = $sections->pluck('type')->sort()->values()->all();
        $expected = collect([Section::TYPE_RW, Section::TYPE_MATH])->sort()->values()->all();
        if ($types !== $expected || $sections->count() !== 2 || $flows->contains(fn ($flow) => $flow !== self::FLOW_ADAPTIVE)) {
            $this->fail('Adaptive full tests require complete adaptive Reading & Writing and Math sections. Convert this draft to Normal Full if only one Module 2 is available.');
        }
    }

    private function validateShortTest(Collection $sections, Collection $flows): void
    {
        $types = $sections->pluck('type')->sort()->values()->all();
        $expected = collect([Section::TYPE_RW, Section::TYPE_MATH])->sort()->values()->all();
        if ($types !== $expected || $sections->count() !== 2 || $flows->contains(fn ($flow) => $flow !== self::FLOW_LINEAR)
            || $sections->contains(fn ($section) => $section->modules->count() !== 1)) {
            $this->fail('Short tests require one standard module in each section.');
        }
    }

    private function validateModuleOnly(Collection $sections): void
    {
        if ($sections->count() !== 1 || $sections->first()->modules->count() !== 1) {
            $this->fail('Module-only tests require exactly one section and one module.');
        }
    }

    private function validateSectionOnly(Collection $sections): void
    {
        if ($sections->count() !== 1) {
            $this->fail('Section-only tests require exactly one section.');
        }
    }

    private function validateCustom(Collection $sections, Collection $flows): void
    {
        if ($sections->count() > 2 || $flows->contains(fn ($flow) => $flow !== self::FLOW_LINEAR)) {
            $this->fail('Custom tests support one or two linear sections.');
        }
    }

    private function isAdaptiveRows(Collection $modules): bool
    {
        if ($modules->count() < 2 || $modules->count() > 3) {
            return false;
        }
        $m1 = $modules->where('module_number', 1)->where('difficulty_level', Module::DIFFICULTY_STANDARD);
        $easy = $modules->where('module_number', 2)->where('difficulty_level', Module::DIFFICULTY_EASY);
        $hard = $modules->where('module_number', 2)->where('difficulty_level', Module::DIFFICULTY_HARD);

        return $m1->count() === 1
            && $easy->count() <= 1
            && $hard->count() <= 1
            && ($easy->count() + $hard->count()) >= 1
            && $modules->count() === 1 + $easy->count() + $hard->count();
    }

    private function isStrictAdaptiveRows(Collection $modules): bool
    {
        return $modules->count() === 3
            && $modules->where('module_number', 1)->where('difficulty_level', Module::DIFFICULTY_STANDARD)->count() === 1
            && $modules->where('module_number', 2)->where('difficulty_level', Module::DIFFICULTY_EASY)->count() === 1
            && $modules->where('module_number', 2)->where('difficulty_level', Module::DIFFICULTY_HARD)->count() === 1;
    }

    private function isNormalFullRows(Collection $modules): bool
    {
        return $modules->count() === 2
            && $modules->where('module_number', 1)->where('difficulty_level', Module::DIFFICULTY_STANDARD)->count() === 1
            && $modules->where('module_number', 2)->count() === 1;
    }

    private function validateAdaptiveMeasurement(Section $section): void
    {
        foreach ($section->modules as $module) {
            if ($module->questions->where('is_pretest', true)->count() !== Module::PRETEST_QUESTIONS_PER_MODULE) {
                $this->fail("{$section->name}, Module {$module->module_number} requires exactly ".Module::PRETEST_QUESTIONS_PER_MODULE.' pretest questions for Adaptive Full.');
            }
            foreach ($module->questions->where('is_pretest', false) as $question) {
                if (! is_numeric($question->irt_a) || ! is_numeric($question->irt_b) || ! is_numeric($question->irt_c)
                    || (float) $question->irt_a <= 0.0 || (float) $question->irt_a > 4.0
                    || (float) $question->irt_b < -6.0 || (float) $question->irt_b > 6.0
                    || (float) $question->irt_c < 0.0 || (float) $question->irt_c >= 1.0) {
                    $this->fail("Question {$question->id} has invalid IRT parameters for Adaptive Full.");
                }
            }
        }

        $easy = $section->modules->first(fn ($module) => (int) $module->module_number === 2 && $module->difficulty_level === Module::DIFFICULTY_EASY);
        $hard = $section->modules->first(fn ($module) => (int) $module->module_number === 2 && $module->difficulty_level === Module::DIFFICULTY_HARD);
        $easyMean = $easy?->questions->where('is_pretest', false)->avg('irt_b');
        $hardMean = $hard?->questions->where('is_pretest', false)->avg('irt_b');
        if ($easyMean === null || $hardMean === null || ((float) $hardMean - (float) $easyMean) < 0.5) {
            $this->fail("{$section->name} hard/easy branches require mean IRT difficulty separation of at least 0.5.");
        }
    }

    private function isLinearRows(Collection $modules): bool
    {
        if ($modules->isEmpty() || $modules->contains(fn ($module) => $module->difficulty_level !== Module::DIFFICULTY_STANDARD)) {
            return false;
        }
        $numbers = $modules->pluck('module_number')->map(fn ($number) => (int) $number)->sort()->values();

        return $numbers->all() === range(1, $modules->count());
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['test_structure' => $message]);
    }
}
