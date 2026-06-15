<?php

namespace Tests\Feature;

use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPracticeVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_choose_test_only_lists_public_active_tests(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $privateTest = $this->createPracticeTest('Admin Private Active Test', false);
        $publicTest = $this->createPracticeTest('Admin Public Active Test', true);

        $response = $this->actingAs($student)->get(route('home.practice'));

        $response->assertOk();
        $response->assertDontSee($privateTest->title);
        $response->assertSee($publicTest->title);
    }

    public function test_student_can_take_public_test_module_but_private_module_returns_404(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $privateTest = $this->createPracticeTest('Admin Private Active Test', false);
        $publicTest = $this->createPracticeTest('Admin Public Active Test', true);

        $privateModule = $privateTest->sections()->first()->modules()->first();
        $publicModule = $publicTest->sections()->first()->modules()->first();

        $this->actingAs($student)
            ->get(route('engine.session', ['ulid' => $privateModule->ulid]))
            ->assertNotFound();

        $this->actingAs($student)
            ->get(route('engine.session', ['ulid' => $publicModule->ulid]))
            ->assertOk();
    }

    private function createPracticeTest(string $title, bool $isPublic): Test
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $test = Test::create([
            'title' => $title,
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'active',
            'created_by' => $admin->id,
            'is_public' => $isPublic,
        ]);

        $section = Section::create([
            'test_id' => $test->id,
            'name' => 'Reading and Writing',
            'type' => Section::TYPE_RW,
            'order' => 1,
            'created_by' => $admin->id,
            'is_public' => false,
        ]);

        $module = Module::create([
            'section_id' => $section->id,
            'key' => 'TEST_' . $test->id . '_RW_M1',
            'module_number' => 1,
            'difficulty_level' => Module::DIFFICULTY_STANDARD,
            'duration_minutes' => 32,
            'total_questions' => 1,
            'order' => 1,
            'created_by' => $admin->id,
            'is_public' => false,
        ]);

        $module->sections()->syncWithoutDetaching([$section->id]);

        $passage = Passage::create([
            'content' => 'A short reading passage.',
            'passage_type' => 'single',
            'genre' => 'humanities',
        ]);

        $question = Question::create([
            'passage_id' => $passage->id,
            'stem' => 'Which choice best supports the claim?',
            'question_type' => Question::TYPE_MCQ,
            'difficulty' => 'easy',
            'is_pretest' => false,
            'is_complete' => true,
            'section_type' => Section::TYPE_RW,
            'skill_domain' => 'information_and_ideas',
            'created_by' => $admin->id,
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

        $module->questions()->attach($question->id, ['position' => 1]);

        return $test;
    }
}
