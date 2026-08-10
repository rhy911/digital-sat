<?php

namespace Tests\Feature;

use App\Jobs\ScoreModuleJob;
use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\SprCorrectAnswer;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Services\ModuleScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A student who edits an SPR answer was graded on the value they typed FIRST.
 *
 * Two writers, no ordering. Autosave fired once per keystroke with no in-flight
 * guard, so several POSTs raced and the upsert in HandlesAnswers is last-writer-
 * wins; and an autosave that started before the edit could still land after the
 * submission had saved the corrected answer, because the attempt only leaves the
 * module later, inside scoreAndAdvance().
 *
 * The request ordering itself is fixed client-side (single-flight autosave in
 * resources/js/test/navigation.js, not reachable from PHPUnit). What is asserted
 * here is the server-side backstop: while a submission is in flight, autosave
 * must not write.
 */
class EngineAutosaveSubmitRaceTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Test $test;

    private Module $moduleOne;

    private Question $sprQuestion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create(['email_verified_at' => now()]);
        $this->test = Test::create([
            'title' => 'Autosave Race Test',
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
        $this->attachMcqQuestion($this->createModule($section, 2, Module::DIFFICULTY_EASY, 2));
        $this->attachMcqQuestion($this->createModule($section, 2, Module::DIFFICULTY_HARD, 3));

        $this->sprQuestion = Question::create([
            'stem' => 'Enter one half.',
            'question_type' => Question::TYPE_SPR,
            'difficulty' => 'medium',
            'section_type' => Section::TYPE_MATH,
            'skill_domain' => 'algebra',
            'is_complete' => true,
        ]);
        SprCorrectAnswer::create([
            'question_id' => $this->sprQuestion->id,
            'answer' => '1/2',
        ]);
        $this->moduleOne->questions()->attach($this->sprQuestion->id, ['position' => 1]);
    }

    public function test_submitted_spr_answer_replaces_the_earlier_autosaved_value(): void
    {
        Queue::fake();
        $attempt = $this->attempt();

        $this->autosave($attempt, '5')->assertOk();
        $this->assertStoredAnswer($attempt, '5', false);

        $this->actingAs($this->student)
            ->postJson(route('engine.test.submit-module'), [
                'user_test_id' => $attempt->id,
                'module_id' => $this->moduleOne->id,
                'answers' => [(string) $this->sprQuestion->id => '1/2'],
            ])->assertOk();

        $this->assertStoredAnswer($attempt, '1/2', true);
    }

    public function test_autosave_cannot_overwrite_answers_while_a_submission_is_in_flight(): void
    {
        // Force the queued path so the submission is still "in flight" after the
        // request returns — the exact window a straggler autosave used to land in.
        config(['scoring.inline_non_final' => false]);
        Queue::fake();
        $attempt = $this->attempt();

        $this->actingAs($this->student)
            ->postJson(route('engine.test.submit-module'), [
                'user_test_id' => $attempt->id,
                'module_id' => $this->moduleOne->id,
                'answers' => [(string) $this->sprQuestion->id => '1/2'],
            ])->assertOk()->assertJsonPath('status', 'scoring');

        Queue::assertPushed(ScoreModuleJob::class);
        $this->assertTrue(Cache::has(
            ModuleScoringService::submitMarkerKey($attempt->id, $this->moduleOne->id)
        ), 'Submit must mark the module as being processed.');

        // The stale autosave: same module, still active on the attempt, carrying the
        // value the student had typed before their last edit.
        $this->autosave($attempt, '5')
            ->assertStatus(409)
            ->assertJsonPath('error', 'submission_in_progress');

        $this->assertStoredAnswer($attempt, '1/2', true);
    }

    public function test_marker_is_released_once_scoring_finishes_and_autosave_stays_blocked(): void
    {
        $attempt = $this->attempt();

        // Sync queue: scoring runs to completion inside the request.
        $this->actingAs($this->student)
            ->postJson(route('engine.test.submit-module'), [
                'user_test_id' => $attempt->id,
                'module_id' => $this->moduleOne->id,
                'answers' => [(string) $this->sprQuestion->id => '1/2'],
            ])->assertOk();

        $this->assertFalse(Cache::has(
            ModuleScoringService::submitMarkerKey($attempt->id, $this->moduleOne->id)
        ), 'Marker must not outlive scoring, or autosave stays blocked for its whole TTL.');

        // Marker gone, but the attempt has moved on, so a late autosave is still
        // refused — by the module check this time.
        $this->autosave($attempt, '5')
            ->assertStatus(409)
            ->assertJsonPath('error', 'module_progression_conflict');

        $this->assertStoredAnswer($attempt, '1/2', true);
    }

    private function autosave(UserTest $attempt, string $answer)
    {
        return $this->actingAs($this->student)
            ->postJson(route('engine.test.autosave-module'), [
                'user_test_id' => $attempt->id,
                'module_id' => $this->moduleOne->id,
                'answers' => [(string) $this->sprQuestion->id => $answer],
            ]);
    }

    private function assertStoredAnswer(UserTest $attempt, string $expected, bool $isCorrect): void
    {
        $this->assertDatabaseHas('user_test_answers', [
            'user_test_id' => $attempt->id,
            'module_id' => $this->moduleOne->id,
            'question_id' => $this->sprQuestion->id,
            'selected_answer' => $expected,
            'is_correct' => $isCorrect ? 1 : 0,
        ]);
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

    private function attachMcqQuestion(Module $module): Question
    {
        $question = Question::create([
            'stem' => "Module {$module->id} question",
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
