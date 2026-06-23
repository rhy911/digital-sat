<?php

namespace Tests\Feature;

use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Models\UserTestModuleSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleProgressionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Test $test;

    private Module $moduleOne;

    private Module $easyModule;

    private Module $hardModule;

    private Question $moduleOneQuestion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create(['email_verified_at' => now()]);
        $this->test = Test::create([
            'title' => 'Progression Security Test',
            'test_type' => 'section_only',
            'status' => 'active',
            'is_public' => true,
        ]);
        $section = Section::create([
            'test_id' => $this->test->id,
            'name' => 'Math',
            'type' => Section::TYPE_MATH,
            'order' => 1,
            'is_public' => true,
        ]);

        $this->moduleOne = $this->createModule($section, 1, Module::DIFFICULTY_STANDARD, 1);
        $this->easyModule = $this->createModule($section, 2, Module::DIFFICULTY_EASY, 2);
        $this->hardModule = $this->createModule($section, 2, Module::DIFFICULTY_HARD, 3);
        $this->moduleOneQuestion = $this->attachQuestion($this->moduleOne, 'Module one question');
        $this->attachQuestion($this->easyModule, 'Easy module question');
        $this->attachQuestion($this->hardModule, 'Hard module question');
    }

    public function test_get_cannot_jump_to_later_module_or_change_progression(): void
    {
        $attempt = $this->attempt();

        $this->actingAs($this->student)
            ->get(route('engine.session', ['ulid' => $this->hardModule->ulid, 'attempt' => $attempt->ulid]))
            ->assertStatus(409);

        $attempt->refresh();
        $this->assertSame($this->moduleOne->id, $attempt->current_module_id);
        $this->assertNull($attempt->current_module_started_at);
    }

    public function test_wrong_module_cannot_autosave_submit_or_complete_attempt(): void
    {
        $attempt = $this->attempt();
        $question = $this->hardModule->questions()->firstOrFail();
        $payload = [
            'user_test_id' => $attempt->id,
            'module_id' => $this->hardModule->id,
            'answers' => [(string) $question->id => 'A'],
        ];

        $this->actingAs($this->student)
            ->postJson(route('engine.test.autosave-module'), $payload)
            ->assertStatus(409)
            ->assertJsonPath('error', 'module_progression_conflict');
        $this->actingAs($this->student)
            ->postJson(route('engine.test.submit-module'), $payload)
            ->assertStatus(409)
            ->assertJsonPath('error', 'module_progression_conflict');

        $this->assertSame('in_progress', $attempt->fresh()->status);
        $this->assertSame(0, UserTestAnswer::where('user_test_id', $attempt->id)->count());
    }

    public function test_submit_atomically_issues_routed_module_and_is_idempotent(): void
    {
        $attempt = $this->attempt();
        $payload = [
            'user_test_id' => $attempt->id,
            'module_id' => $this->moduleOne->id,
            'answers' => [(string) $this->moduleOneQuestion->id => 'A'],
        ];

        $first = $this->actingAs($this->student)
            ->postJson(route('engine.test.submit-module'), $payload)
            ->assertOk();
        $issuedModule = Module::where('ulid', $first->json('next_module_id'))->firstOrFail();
        $this->assertSame($issuedModule->id, $attempt->fresh()->current_module_id);

        $payload['answers'][(string) $this->moduleOneQuestion->id] = 'B';
        $second = $this->actingAs($this->student)
            ->postJson(route('engine.test.submit-module'), $payload)
            ->assertOk();

        $this->assertEquals($first->json(), $second->json());
        $this->assertSame(1, UserTestModuleSubmission::where('user_test_id', $attempt->id)->count());
        $this->assertDatabaseHas('user_test_answers', [
            'user_test_id' => $attempt->id,
            'module_id' => $this->moduleOne->id,
            'question_id' => $this->moduleOneQuestion->id,
            'selected_answer' => 'A',
        ]);
    }

    public function test_wrong_adaptive_branch_and_old_stale_tab_are_rejected(): void
    {
        $attempt = $this->attempt();
        $firstPayload = [
            'user_test_id' => $attempt->id,
            'module_id' => $this->moduleOne->id,
            'answers' => [(string) $this->moduleOneQuestion->id => 'A'],
        ];
        $first = $this->actingAs($this->student)
            ->postJson(route('engine.test.submit-module'), $firstPayload)
            ->assertOk();

        $issued = Module::where('ulid', $first->json('next_module_id'))->firstOrFail();
        $wrongBranch = $issued->is($this->easyModule) ? $this->hardModule : $this->easyModule;
        $this->actingAs($this->student)
            ->get(route('engine.session', ['ulid' => $wrongBranch->ulid, 'attempt' => $attempt->ulid]))
            ->assertStatus(409);

        $issuedQuestion = $issued->questions()->firstOrFail();
        $this->actingAs($this->student)->postJson(route('engine.test.submit-module'), [
            'user_test_id' => $attempt->id,
            'module_id' => $issued->id,
            'answers' => [(string) $issuedQuestion->id => 'A'],
        ])->assertOk()->assertJsonPath('test_completed', true);

        $this->actingAs($this->student)
            ->postJson(route('engine.test.submit-module'), $firstPayload)
            ->assertStatus(409)
            ->assertJsonPath('error', 'module_progression_conflict');
    }

    private function attempt(): UserTest
    {
        return UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'in_progress',
            'current_module_id' => $this->moduleOne->id,
        ]);
    }

    private function createModule(Section $section, int $number, string $difficulty, int $order): Module
    {
        return Module::create([
            'section_id' => $section->id,
            'module_number' => $number,
            'difficulty_level' => $difficulty,
            'duration_minutes' => 20,
            'total_questions' => 1,
            'order' => $order,
            'is_public' => true,
        ]);
    }

    private function attachQuestion(Module $module, string $stem): Question
    {
        $question = Question::create([
            'stem' => $stem,
            'question_type' => Question::TYPE_MCQ,
            'difficulty' => 'medium',
            'section_type' => Section::TYPE_MATH,
            'skill_domain' => 'algebra',
            'is_complete' => true,
        ]);
        foreach (['A', 'B', 'C', 'D'] as $index => $label) {
            AnswerChoice::create([
                'question_id' => $question->id,
                'label' => $label,
                'content' => $label,
                'is_correct' => $index === 0,
                'order' => $index + 1,
            ]);
        }
        $module->questions()->attach($question->id, ['position' => 1]);

        return $question;
    }
}
