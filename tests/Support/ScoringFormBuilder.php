<?php

namespace Tests\Support;

use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\SprCorrectAnswer;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Services\TestProgressionService;

/**
 * Deterministic fixture builder for the scoring pipeline.
 *
 * Builds a complete SAT form (adaptive_full_length or full_length) with official
 * module sizes, explicit per-question IRT parameters, 2 pretest items per module,
 * and a mix of difficulties + MCQ/SPR types — then plays a full student attempt
 * through the real TestProgressionService (module-by-module submit), so the
 * finalized UserTest carries production-computed theta / scaled scores / bands.
 *
 * The output is fully deterministic: given the same form + the same per-module
 * correct-count closure, the scores never change. Feature tests can therefore pin
 * exact golden values and detect any regression in the scoring math.
 */
class ScoringFormBuilder
{
    /** Explicit 3PL parameters by difficulty tier (MCQ). */
    private const IRT_MCQ = [
        'easy' => ['a' => 1.00, 'b' => -1.10, 'c' => 0.20],
        'medium' => ['a' => 1.10, 'b' => 0.00, 'c' => 0.20],
        'hard' => ['a' => 1.20, 'b' => 1.20, 'c' => 0.15],
    ];

    /** SPR items have no guessing floor (c = 0) and are a touch more discriminating. */
    private const IRT_SPR = [
        'easy' => ['a' => 1.20, 'b' => -1.10, 'c' => 0.00],
        'medium' => ['a' => 1.30, 'b' => 0.00, 'c' => 0.00],
        'hard' => ['a' => 1.40, 'b' => 1.20, 'c' => 0.00],
    ];

    /** Difficulty palettes cycled across a module's scored items. */
    private const PALETTE = [
        'standard' => ['easy', 'medium', 'medium', 'hard'],
        'easy' => ['easy', 'easy', 'medium'],
        'hard' => ['medium', 'hard', 'hard'],
    ];

    /**
     * Build a complete adaptive full-length form: RW + Math, each with a standard
     * Module 1 and both easy + hard Module 2 branches (required by the strict
     * adaptive structure gate).
     */
    public static function adaptiveFull(string $title): Test
    {
        $test = Test::create([
            'title' => $title,
            'test_type' => Test::TYPE_ADAPTIVE_FULL,
            'status' => 'active',
        ]);

        foreach ([[Section::TYPE_RW, 1, Module::RW_QUESTIONS], [Section::TYPE_MATH, 2, Module::MATH_QUESTIONS]] as [$type, $order, $count]) {
            $section = Section::create(['test_id' => $test->id, 'name' => $type, 'type' => $type, 'order' => $order]);
            self::module($section, 1, Module::DIFFICULTY_STANDARD, 1, $count, Module::PRETEST_QUESTIONS_PER_MODULE);
            self::module($section, 2, Module::DIFFICULTY_EASY, 2, $count, Module::PRETEST_QUESTIONS_PER_MODULE);
            self::module($section, 2, Module::DIFFICULTY_HARD, 3, $count, Module::PRETEST_QUESTIONS_PER_MODULE);
        }

        return $test->load('sections.modules.questions');
    }

    /**
     * Build a complete normal full-length form: RW + Math, each a linear standard
     * Module 1 -> standard Module 2.
     */
    public static function normalFull(string $title): Test
    {
        $test = Test::create([
            'title' => $title,
            'test_type' => Test::TYPE_FULL,
            'status' => 'active',
        ]);

        foreach ([[Section::TYPE_RW, 1, Module::RW_QUESTIONS], [Section::TYPE_MATH, 2, Module::MATH_QUESTIONS]] as [$type, $order, $count]) {
            $section = Section::create(['test_id' => $test->id, 'name' => $type, 'type' => $type, 'order' => $order]);
            // Normal (full_length) forms have no pretest items — every question is scored.
            self::module($section, 1, Module::DIFFICULTY_STANDARD, 1, $count, 0);
            self::module($section, 2, Module::DIFFICULTY_STANDARD, 2, $count, 0);
        }

        return $test->load('sections.modules.questions');
    }

    /**
     * Play a full attempt through the real progression/scoring services.
     *
     * $correctFor receives each presented Module and returns how many of its scored
     * (non-pretest) questions the student got right; the routed Module 2 branch is
     * discovered at runtime exactly as the live engine decides it.
     */
    public static function play(Test $test, User $student, callable $correctFor): UserTest
    {
        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'attempt_type' => 'full',
            'status' => 'in_progress',
        ]);

        $service = app(TestProgressionService::class);
        $firstSection = $test->load('sections.modules.questions')->sections
            ->sortBy('order')->values()->first();
        $module = $firstSection->modules->firstWhere('module_number', 1);
        $module->setRelation('section', $firstSection);

        while ($module) {
            self::answerModule($attempt, $module, (int) $correctFor($module));
            $result = $service->submit($attempt, $module);
            if (! empty($result['test_completed'])) {
                break;
            }
            $module = Module::with('questions', 'section')
                ->where('ulid', $result['next_module_id'])
                ->firstOrFail();
        }

        return $attempt->fresh();
    }

    private static function module(Section $section, int $number, string $difficulty, int $order, int $count, int $pretest): Module
    {
        $module = Module::create([
            'section_id' => $section->id,
            'module_number' => $number,
            'difficulty_level' => $difficulty,
            'duration_minutes' => $section->type === Section::TYPE_RW ? Module::RW_DURATION : Module::MATH_DURATION,
            'total_questions' => $count,
            'order' => $order,
        ]);

        $palette = self::PALETTE[$difficulty];
        $sprSlot = $section->type === Section::TYPE_MATH ? 3 : -1; // one SPR item in each Math module

        for ($i = 0; $i < $count; $i++) {
            $isPretest = $i < $pretest;
            $tier = $palette[$i % count($palette)];
            $isSpr = ! $isPretest && $i === $sprSlot;

            $module->questions()->attach(
                self::question($section->type, $tier, $isSpr, $isPretest)->id,
                ['position' => $i + 1],
            );
        }

        return $module;
    }

    private static function question(string $sectionType, string $tier, bool $isSpr, bool $isPretest): Question
    {
        $irt = ($isSpr ? self::IRT_SPR : self::IRT_MCQ)[$tier];

        $question = Question::create([
            'stem' => 'Fixture item',
            'question_type' => $isSpr ? Question::TYPE_SPR : Question::TYPE_MCQ,
            'difficulty' => $tier,
            'is_pretest' => $isPretest,
            'is_complete' => true,
            'section_type' => $sectionType,
            'skill_domain' => 'fixture',
            'irt_a' => $irt['a'],
            'irt_b' => $irt['b'],
            'irt_c' => $irt['c'],
        ]);

        if ($isSpr) {
            SprCorrectAnswer::create(['question_id' => $question->id, 'answer' => '5']);
        } else {
            foreach (['A', 'B', 'C', 'D'] as $index => $label) {
                AnswerChoice::create([
                    'question_id' => $question->id,
                    'label' => $label,
                    'content' => "Choice {$label}",
                    'is_correct' => $index === 0,
                    'order' => $index + 1,
                ]);
            }
        }

        return $question;
    }

    /**
     * Write a response row for every question in the module (required — finalize
     * rejects a module missing any response). The first $correct scored questions
     * are marked right; pretest rows exist but are excluded from scoring.
     */
    private static function answerModule(UserTest $attempt, Module $module, int $correct): void
    {
        $scoredSeen = 0;
        foreach ($module->questions()->orderByPivot('position')->get() as $question) {
            if ($question->is_pretest) {
                $isCorrect = true;
            } else {
                $isCorrect = $scoredSeen < $correct;
                $scoredSeen++;
            }

            UserTestAnswer::updateOrCreate(
                ['user_test_id' => $attempt->id, 'question_id' => $question->id],
                [
                    'module_id' => $module->id,
                    'selected_answer' => self::answerLabel($question, $isCorrect),
                    'is_correct' => $isCorrect,
                ],
            );
        }
    }

    private static function answerLabel(Question $question, bool $isCorrect): string
    {
        if ($question->question_type === Question::TYPE_SPR) {
            return $isCorrect ? '5' : '9';
        }

        return $isCorrect ? 'A' : 'B';
    }
}
