<?php

namespace Tests\Feature;

use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionExplanation;
use App\Models\Section;
use App\Models\SprCorrectAnswer;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Services\TestManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HistoricalDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Test $test;
    private Section $section;
    private Module $module;
    private Question $mcqQuestion;
    private Question $sprQuestion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $this->test = Test::create([
            'title' => 'Historical Integrity Test',
            'test_type' => 'module_only',
            'status' => 'active',
            'is_public' => true,
        ]);

        $this->section = Section::create([
            'test_id' => $this->test->id,
            'name' => 'Math',
            'type' => Section::TYPE_MATH,
            'order' => 1,
        ]);

        $this->module = Module::create([
            'section_id' => $this->section->id,
            'key' => 'HISTORICAL_M1',
            'module_number' => 1,
            'difficulty_level' => Module::DIFFICULTY_STANDARD,
            'duration_minutes' => 35,
            'total_questions' => 2,
            'order' => 1,
        ]);
        $this->module->sections()->syncWithoutDetaching([$this->section->id]);

        // MCQ Question
        $this->mcqQuestion = Question::create([
            'stem' => 'Original MCQ Stem',
            'question_type' => Question::TYPE_MCQ,
            'difficulty' => 'easy',
            'is_pretest' => false,
            'is_complete' => true,
            'section_type' => Section::TYPE_MATH,
            'skill_domain' => 'algebra',
        ]);

        foreach (['A', 'B', 'C', 'D'] as $index => $label) {
            AnswerChoice::create([
                'question_id' => $this->mcqQuestion->id,
                'label' => $label,
                'content' => "Original Choice {$label}",
                'is_correct' => $index === 0,
                'order' => $index + 1,
            ]);
        }

        QuestionExplanation::create([
            'question_id' => $this->mcqQuestion->id,
            'explanation' => 'Original Explanation',
        ]);

        // SPR Question
        $this->sprQuestion = Question::create([
            'stem' => 'Original SPR Stem',
            'question_type' => Question::TYPE_SPR,
            'difficulty' => 'easy',
            'is_pretest' => false,
            'is_complete' => true,
            'section_type' => Section::TYPE_MATH,
            'skill_domain' => 'algebra',
        ]);

        SprCorrectAnswer::create([
            'question_id' => $this->sprQuestion->id,
            'answer' => '42',
            'answer_type' => 'exact',
        ]);

        $this->module->questions()->attach($this->mcqQuestion->id, ['position' => 1]);
        $this->module->questions()->attach($this->sprQuestion->id, ['position' => 2]);
    }

    public function test_submitting_answers_saves_question_snapshot_and_isolates_from_edits()
    {
        $userTest = UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'in_progress',
            'current_module_id' => $this->module->id,
            'current_module_started_at' => now(),
        ]);

        $response = $this->actingAs($this->student)->postJson(route('engine.test.submit-module'), [
            'user_test_id' => $userTest->id,
            'module_id' => $this->module->id,
            'answers' => [
                (string) $this->mcqQuestion->id => 'A',
                (string) $this->sprQuestion->id => '42',
            ],
        ]);

        $response->assertOk();

        // Verify snapshots exist in database
        $mcqAnswer = UserTestAnswer::where('user_test_id', $userTest->id)
            ->where('question_id', $this->mcqQuestion->id)
            ->first();

        $this->assertNotNull($mcqAnswer->question_snapshot);
        $this->assertEquals('Original MCQ Stem', $mcqAnswer->question_snapshot['stem']);
        $this->assertEquals('Original Explanation', $mcqAnswer->question_snapshot['explanation']['explanation']);

        // Modify the original question in the database
        $this->mcqQuestion->update(['stem' => 'Modified MCQ Stem']);
        $this->mcqQuestion->explanation->update(['explanation' => 'Modified Explanation']);
        $this->mcqQuestion->answerChoices()->where('label', 'A')->update(['content' => 'Modified Choice A']);

        // Access the question property of the answer and verify it has the snapshotted version, not live database values
        $hydratedQuestion = $mcqAnswer->fresh()->question;

        $this->assertEquals('Original MCQ Stem', $hydratedQuestion->stem);
        $this->assertEquals('Original Explanation', $hydratedQuestion->explanation->explanation);
        $this->assertEquals('Original Choice A', $hydratedQuestion->answerChoices->firstWhere('label', 'A')->content);
    }

    public function test_deleting_test_with_attempts_is_blocked()
    {
        $userTest = UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'in_progress',
            'current_module_id' => $this->module->id,
            'current_module_started_at' => now(),
        ]);

        $service = app(TestManagementService::class);

        $this->expectException(ValidationException::class);
        $service->deleteTest($this->test->id, true);
    }

    public function test_deleting_question_with_answers_is_blocked()
    {
        $userTest = UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'in_progress',
            'current_module_id' => $this->module->id,
            'current_module_started_at' => now(),
        ]);

        UserTestAnswer::create([
            'user_test_id' => $userTest->id,
            'module_id' => $this->module->id,
            'question_id' => $this->mcqQuestion->id,
            'selected_answer' => 'A',
            'is_correct' => true,
        ]);

        // Attempting to delete via QuestionController destroy method should return 422
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->mcqQuestion->update(['created_by' => $teacher->id]);

        $response = $this->actingAs($teacher)->deleteJson(route('home-dashboard.questions.delete', $this->mcqQuestion));
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Cannot delete question with existing student attempts.']);
    }

    public function test_soft_deletes_on_test_retains_completed_reviews()
    {
        $userTest = UserTest::create([
            'user_id' => $this->student->id,
            'test_id' => $this->test->id,
            'status' => 'completed',
        ]);

        // Delete the test directly (since it has attempts, service blocks it, but if we delete it directly or it was soft deleted)
        $this->test->delete(); // Soft deletes the test

        $this->assertSoftDeleted($this->test);

        // Verify we can still resolve the test relationship on UserTest using withTrashed()
        $this->assertNotNull($userTest->fresh()->test);
        $this->assertEquals('Historical Integrity Test', $userTest->fresh()->test->title);
    }
}
