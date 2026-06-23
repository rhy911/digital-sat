<?php

namespace Tests\Feature;

use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\SprCorrectAnswer;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleAnswerPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_module_persists_omitted_mcq_and_spr_answers(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $test = Test::create([
            'title' => 'Omitted Answer Test',
            'test_type' => 'module_only',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);

        $section = Section::create([
            'test_id' => $test->id,
            'name' => 'Math',
            'type' => Section::TYPE_MATH,
            'order' => 1,
            'is_public' => true,
        ]);

        $module = Module::create([
            'section_id' => $section->id,
            'key' => 'OMITTED_MATH_M2',
            'module_number' => 2,
            'difficulty_level' => Module::DIFFICULTY_STANDARD,
            'duration_minutes' => 20,
            'total_questions' => 4,
            'order' => 1,
            'is_public' => true,
        ]);
        $module->sections()->syncWithoutDetaching([$section->id]);

        $answeredMcq = $this->createMcq('Answered MCQ');
        $omittedMcq = $this->createMcq('Omitted MCQ');
        $answeredSpr = $this->createSpr('Answered SPR', '7');
        $omittedSpr = $this->createSpr('Omitted SPR', '11');

        foreach ([$answeredMcq, $omittedMcq, $answeredSpr, $omittedSpr] as $position => $question) {
            $module->questions()->attach($question->id, ['position' => $position + 1]);
        }

        $userTest = UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
            'current_module_id' => $module->id,
            'current_module_started_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson(route('engine.test.submit-module'), [
            'user_test_id' => $userTest->id,
            'module_id' => $module->id,
            'answers' => [
                (string) $answeredMcq->id => 'A',
                (string) $omittedMcq->id => null,
                (string) $answeredSpr->id => '7',
                (string) $omittedSpr->id => null,
            ],
        ]);

        $response->assertOk();
        $this->assertSame(4, UserTestAnswer::where('user_test_id', $userTest->id)->count());

        $this->assertDatabaseHas('user_test_answers', [
            'user_test_id' => $userTest->id,
            'module_id' => $module->id,
            'question_id' => $omittedMcq->id,
            'selected_answer' => null,
            'is_correct' => false,
        ]);

        $this->assertDatabaseHas('user_test_answers', [
            'user_test_id' => $userTest->id,
            'module_id' => $module->id,
            'question_id' => $omittedSpr->id,
            'selected_answer' => null,
            'is_correct' => false,
        ]);
    }

    private function createMcq(string $stem): Question
    {
        $question = Question::create([
            'stem' => $stem,
            'question_type' => Question::TYPE_MCQ,
            'difficulty' => 'easy',
            'is_pretest' => false,
            'is_complete' => true,
            'section_type' => Section::TYPE_MATH,
            'skill_domain' => 'algebra',
        ]);

        foreach (['A', 'B', 'C', 'D'] as $index => $label) {
            AnswerChoice::create([
                'question_id' => $question->id,
                'label' => $label,
                'content' => "Choice {$label}",
                'is_correct' => $index === 0,
                'order' => $index + 1,
            ]);
        }

        return $question;
    }

    private function createSpr(string $stem, string $answer): Question
    {
        $question = Question::create([
            'stem' => $stem,
            'question_type' => Question::TYPE_SPR,
            'difficulty' => 'easy',
            'is_pretest' => false,
            'is_complete' => true,
            'section_type' => Section::TYPE_MATH,
            'skill_domain' => 'algebra',
        ]);

        SprCorrectAnswer::create([
            'question_id' => $question->id,
            'answer' => $answer,
            'answer_type' => 'exact',
        ]);

        return $question;
    }

    public function test_answers_are_isolated_by_module_for_shared_questions(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $test = Test::create([
            'title' => 'Shared Question Test',
            'test_type' => 'short_test',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);

        $section = Section::create([
            'test_id' => $test->id,
            'name' => 'Math',
            'type' => Section::TYPE_MATH,
            'order' => 1,
            'is_public' => true,
        ]);

        $module1 = Module::create([
            'section_id' => $section->id,
            'key' => 'SHARED_MATH_M1',
            'module_number' => 1,
            'difficulty_level' => Module::DIFFICULTY_STANDARD,
            'duration_minutes' => 20,
            'total_questions' => 1,
            'order' => 1,
            'is_public' => true,
        ]);
        $module1->sections()->syncWithoutDetaching([$section->id]);

        $module2 = Module::create([
            'section_id' => $section->id,
            'key' => 'SHARED_MATH_M2',
            'module_number' => 2,
            'difficulty_level' => Module::DIFFICULTY_STANDARD,
            'duration_minutes' => 20,
            'total_questions' => 1,
            'order' => 2,
            'is_public' => true,
        ]);
        $module2->sections()->syncWithoutDetaching([$section->id]);

        $sharedQuestion = $this->createMcq('Shared Question');
        $module1->questions()->attach($sharedQuestion->id, ['position' => 1]);
        $module2->questions()->attach($sharedQuestion->id, ['position' => 1]);

        $userTest = UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
        ]);

        UserTestAnswer::create([
            'user_test_id' => $userTest->id,
            'module_id' => $module1->id,
            'question_id' => $sharedQuestion->id,
            'selected_answer' => 'A',
            'is_correct' => true,
            'question_snapshot' => '{}',
        ]);

        UserTestAnswer::create([
            'user_test_id' => $userTest->id,
            'module_id' => $module2->id,
            'question_id' => $sharedQuestion->id,
            'selected_answer' => 'B',
            'is_correct' => false,
            'question_snapshot' => '{}',
        ]);

        $m1Answers = UserTestAnswer::where('user_test_id', $userTest->id)
            ->where('module_id', $module1->id)
            ->get();
        $this->assertCount(1, $m1Answers);
        $this->assertEquals('A', $m1Answers->first()->selected_answer);

        $m2Answers = UserTestAnswer::where('user_test_id', $userTest->id)
            ->where('module_id', $module2->id)
            ->get();
        $this->assertCount(1, $m2Answers);
        $this->assertEquals('B', $m2Answers->first()->selected_answer);
    }
}
