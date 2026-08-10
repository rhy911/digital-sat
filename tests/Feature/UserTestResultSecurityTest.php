<?php

namespace Tests\Feature;

use App\Models\AnswerChoice;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTestResultSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_practice_result_by_ulid(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        $test = Test::create([
            'title' => 'Owned Result Test',
            'test_type' => 'short_test',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);
        $userTest = UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('my-practice', $userTest))
            ->assertRedirect(route('student.scores.show', $userTest));
    }

    public function test_in_progress_attempt_resumes_into_the_engine(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        [$test, $module] = $this->testWithOneModule('Resumable Test');
        $userTest = UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
            'current_module_id' => $module->id,
        ]);

        $this->actingAs($user)
            ->get(route('my-practice', $userTest))
            ->assertRedirect(route('engine.session', ['ulid' => $module->ulid, 'attempt' => $userTest->ulid]));
    }

    public function test_resume_endpoint_sends_the_owner_into_the_engine(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        [$test, $module] = $this->testWithOneModule('Resume Endpoint Test');
        $userTest = UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($user)
            ->post(route('my-practice.resume', $userTest))
            ->assertRedirect(route('engine.session', ['ulid' => $module->ulid, 'attempt' => $userTest->ulid]));

        // The attempt had no issued module — resuming must issue one, otherwise
        // the engine rejects it with a 409.
        $this->assertSame($module->id, $userTest->fresh()->current_module_id);
    }

    public function test_resume_endpoint_rejects_a_completed_attempt(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        [$test] = $this->testWithOneModule('Finished Resume Test');
        $userTest = UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('my-practice.resume', $userTest))
            ->assertStatus(409);
    }

    public function test_resume_endpoint_rejects_another_users_attempt(): void
    {
        $owner = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        $intruder = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        [$test, $module] = $this->testWithOneModule('Private Resume Test');
        $userTest = UserTest::create([
            'user_id' => $owner->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
            'current_module_id' => $module->id,
        ]);

        $this->actingAs($intruder)
            ->post(route('my-practice.resume', $userTest))
            ->assertForbidden();
    }

    public function test_in_progress_attempt_without_current_module_falls_back_to_the_library(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        $test = Test::create([
            'title' => 'Stalled Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);
        $userTest = UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($user)
            ->get(route('my-practice', $userTest))
            ->assertRedirect(route('home.practice'))
            ->assertSessionHas('error');
    }

    public function test_other_user_cannot_view_practice_result_by_ulid(): void
    {
        $owner = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        $intruder = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        $test = Test::create([
            'title' => 'Private Result Test',
            'test_type' => 'short_test',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);
        $userTest = UserTest::create([
            'user_id' => $owner->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($intruder)
            ->get(route('my-practice', $userTest))
            ->assertForbidden();
    }

    public function test_numeric_practice_result_id_no_longer_resolves(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        $test = Test::create([
            'title' => 'Numeric Result Test',
            'test_type' => 'short_test',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);
        $userTest = UserTest::create([
            'user_id' => $user->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/my-practice/'.$userTest->id)
            ->assertNotFound();
    }

    public function test_owner_can_export_practice_result_pdf(): void
    {
        $user = User::factory()->student()->create();
        $attempt = $this->scoredAttempt($user);

        $response = $this->actingAs($user)
            ->get(route('student.scores.export-pdf', $attempt))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString(
            'score-report-pdf-result-test-'.now()->format('Y-m-d').'.pdf',
            $response->headers->get('content-disposition')
        );
    }

    public function test_other_user_cannot_export_practice_result_pdf(): void
    {
        $owner = User::factory()->student()->create();
        $intruder = User::factory()->student()->create();
        $attempt = $this->scoredAttempt($owner);

        $this->actingAs($intruder)
            ->get(route('student.scores.export-pdf', $attempt))
            ->assertForbidden();
    }

    public function test_assignment_teacher_co_teacher_and_admin_can_export_practice_result_pdf(): void
    {
        $teacher = User::factory()->teacher()->create();
        $coTeacher = User::factory()->teacher()->create();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $test = Test::create([
            'title' => 'Assigned PDF Result',
            'test_type' => 'full_length',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => false,
            'created_by' => $teacher->id,
        ]);
        $classroom = Classroom::create(['owner_id' => $teacher->id, 'name' => 'PDF Cohort']);
        $classroom->coTeachers()->attach($coTeacher->id, ['added_by' => $teacher->id]);
        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'PDF Assignment',
            'attempt_limit' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $attempt = $this->scoredAttempt($student, $test, ['assignment_id' => $assignment->id, 'attempt_number' => 1]);

        $this->actingAs($teacher)
            ->get(route('student.scores.export-pdf', $attempt))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($coTeacher)
            ->get(route('student.scores.export-pdf', $attempt))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)
            ->get(route('student.scores.export-pdf', $attempt))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /**
     * @return array{0: Test, 1: Module}
     */
    private function testWithOneModule(string $title): array
    {
        $test = Test::create([
            'title' => $title,
            'test_type' => 'full_length',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);
        $section = Section::create([
            'test_id' => $test->id,
            'name' => 'Math',
            'type' => 'math',
            'order' => 1,
        ]);
        $module = Module::create([
            'section_id' => $section->id,
            'module_number' => 1,
            'difficulty_level' => 'standard',
            'duration_minutes' => 35,
            'total_questions' => 1,
            'order' => 1,
        ]);

        return [$test, $module];
    }

    private function scoredAttempt(User $student, ?Test $test = null, array $extra = []): UserTest
    {
        $test ??= Test::create([
            'title' => 'PDF Result Test',
            'test_type' => 'full_length',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);
        $section = Section::create([
            'test_id' => $test->id,
            'name' => 'Math',
            'type' => 'math',
            'order' => 1,
            'created_by' => $test->created_by,
        ]);
        $module = Module::create([
            'section_id' => $section->id,
            'module_number' => 1,
            'difficulty_level' => 'standard',
            'duration_minutes' => 35,
            'total_questions' => 1,
            'order' => 1,
            'created_by' => $test->created_by,
        ]);
        $question = Question::create([
            'stem' => 'What is 1 + 1?',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'math',
            'skill_domain' => 'algebra',
            'calculator_allowed' => true,
            'is_complete' => true,
            'is_pretest' => false,
            'created_by' => $test->created_by,
        ]);
        AnswerChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => '2', 'is_correct' => true, 'order' => 1]);
        AnswerChoice::create(['question_id' => $question->id, 'label' => 'B', 'content' => '3', 'is_correct' => false, 'order' => 2]);
        $module->questions()->attach($question->id, ['position' => 1]);

        $attempt = UserTest::create(array_merge([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'completed_at' => now(),
            'total_score' => 1200,
            'score_reading_writing' => 600,
            'score_math' => 600,
            'score_conversion_version' => 'normal_consensus_v1',
            'score_estimate_kind' => 'normal_generic',
        ], $extra));

        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $question->id,
            'selected_answer' => 'A',
            'is_correct' => true,
        ]);

        return $attempt;
    }
}
