<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\ClassroomMembership;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Services\AttemptProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionOnlyAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_section_only_assignment()
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::create([
            'owner_id' => $teacher->id,
            'name' => 'Math & English Class',
            'code' => 'CLASS101',
            'status' => 'active',
        ]);

        $test = Test::create([
            'title' => 'SAT Test 1',
            'test_type' => 'custom_test', // Use custom_test to skip strict question count validation
            'status' => 'active',
            'created_by' => $teacher->id,
        ]);

        // Add dummy section & module & question to satisfy structural validation checks
        $rwSection = Section::create([
            'test_id' => $test->id,
            'name' => 'Reading & Writing',
            'type' => 'reading_writing',
            'order' => 1,
            'created_by' => $teacher->id,
        ]);
        $rwModule = Module::create([
            'section_id' => $rwSection->id,
            'title' => 'Module 1',
            'module_number' => 1,
            'order' => 1,
            'duration_minutes' => 32,
        ]);
        $question = Question::create([
            'stem' => 'Sample question?',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'reading_writing',
            'skill_domain' => 'craft_and_structure',
            'calculator_allowed' => false,
            'is_complete' => true,
            'created_by' => $teacher->id,
        ]);
        $rwModule->questions()->attach($question->id, ['position' => 1]);

        $response = $this->actingAs($teacher)->post(route('teacher.assignments.store', $classroom), [
            'test_id' => $test->id,
            'title' => 'Reading Section Quiz',
            'assign_type' => 'section',
            'section_type' => 'reading_writing',
            'attempt_limit' => 1,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assignments', [
            'classroom_id' => $classroom->id,
            'test_id' => $test->id,
            'assign_type' => 'section',
            'section_type' => 'reading_writing',
        ]);
    }

    public function test_student_attempt_copies_section_settings_and_resolves_starting_module()
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        
        $classroom = Classroom::create([
            'owner_id' => $teacher->id,
            'name' => 'SAT Class',
            'code' => 'SAT2026',
            'status' => 'active',
        ]);
        ClassroomMembership::create([
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        $test = Test::create([
            'title' => 'SAT Test 2',
            'test_type' => 'custom_test',
            'status' => 'active',
            'created_by' => $teacher->id,
        ]);

        $rwSection = Section::create([
            'test_id' => $test->id,
            'name' => 'Reading & Writing',
            'type' => 'reading_writing',
            'order' => 1,
            'created_by' => $teacher->id,
        ]);

        $mathSection = Section::create([
            'test_id' => $test->id,
            'name' => 'Math',
            'type' => 'math',
            'order' => 2,
            'created_by' => $teacher->id,
        ]);

        $rwModule = Module::create([
            'section_id' => $rwSection->id,
            'title' => 'Module 1',
            'module_number' => 1,
            'order' => 1,
            'duration_minutes' => 32,
        ]);

        $mathModule = Module::create([
            'section_id' => $mathSection->id,
            'title' => 'Module 1',
            'module_number' => 1,
            'order' => 1,
            'duration_minutes' => 35,
        ]);

        $question1 = Question::create([
            'stem' => 'RW question?',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'reading_writing',
            'skill_domain' => 'craft_and_structure',
            'calculator_allowed' => false,
            'is_complete' => true,
            'created_by' => $teacher->id,
        ]);
        $rwModule->questions()->attach($question1->id, ['position' => 1]);

        $question2 = Question::create([
            'stem' => 'Math question?',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'math',
            'skill_domain' => 'algebra',
            'calculator_allowed' => true,
            'is_complete' => true,
            'created_by' => $teacher->id,
        ]);
        $mathModule->questions()->attach($question2->id, ['position' => 1]);

        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'assign_type' => 'section',
            'section_type' => 'math', // Math only
            'title' => 'Math Section Homework',
            'attempt_limit' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $assignment->recipients()->create([
            'student_id' => $student->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        // Start attempt as student
        $response = $this->actingAs($student)->post(route('student.assignments.start', $assignment));

        $response->assertRedirect();
        
        $attempt = UserTest::where('assignment_id', $assignment->id)->first();
        $this->assertNotNull($attempt);
        $this->assertEquals('section', $attempt->attempt_type);
        $this->assertEquals('math', $attempt->section_type);

        // Verify starting module is Math Module 1, NOT Reading & Writing Module 1
        $progression = app(AttemptProgressionService::class);
        $startModule = $progression->firstModule($test, $attempt);
        $this->assertEquals($mathModule->id, $startModule->id);
    }

    public function test_auto_merge_combines_completed_sections_into_first_attempt()
    {
        $student = User::factory()->student()->create();

        $test = Test::create([
            'title' => 'SAT Practice Test',
            'test_type' => 'full_length',
            'status' => 'active',
        ]);

        $attemptRw = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'attempt_type' => 'section',
            'section_type' => 'reading_writing',
            'status' => 'completed',
            'attempt_number' => 1,
            'score_reading_writing' => 600,
            'completed_at' => now()->subDay(),
        ]);

        $attemptMath = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'attempt_type' => 'section',
            'section_type' => 'math',
            'status' => 'in_progress',
            'attempt_number' => 1,
        ]);

        // Complete Math section using TestProgressionService
        $progression = app(\App\Services\TestProgressionService::class);
        $mathModule = Module::create([
            'section_id' => Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => 'math', 'order' => 2])->id,
            'title' => 'Module 1',
            'module_number' => 1,
            'order' => 1,
        ]);

        $mathQuestion = Question::create([
            'stem' => 'Math question?',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'math',
            'skill_domain' => 'algebra',
            'calculator_allowed' => true,
            'is_complete' => true,
        ]);
        $mathModule->questions()->attach($mathQuestion->id, ['position' => 1]);

        $mathAnswer = \App\Models\UserTestAnswer::create([
            'user_test_id' => $attemptMath->id,
            'module_id' => $mathModule->id,
            'question_id' => $mathQuestion->id,
            'selected_answer' => 'A',
            'is_correct' => true,
            'time_spent' => 42,
            'question_snapshot' => ['stem' => 'Math question?'],
        ]);
        $mathAnswer->forceFill(['created_at' => now()->subHours(2)])->save();

        // Mock completion fields on Math attempt
        $attemptMath->update([
            'status' => 'completed',
            'score_math' => 650,
            'completed_at' => now(),
        ]);

        // Call autoMergeIfEligible via reflection or completion trigger
        $reflection = new \ReflectionClass($progression);
        $method = $reflection->getMethod('autoMergeIfEligible');
        $method->setAccessible(true);
        $result = $method->invoke($progression, $attemptMath, $test);

        // Verify Attempt RW (the first attempt) has been upgraded to a merged full test result
        $this->assertEquals($attemptRw->id, $result->id);
        $this->assertEquals('full', $result->attempt_type);
        $this->assertNull($result->section_type);
        $this->assertEquals(600, $result->score_reading_writing);
        $this->assertEquals(650, $result->score_math);
        $this->assertEquals(1250, $result->total_score);

        // Verify standalone practice section attemptMath was cleaned up
        $this->assertDatabaseMissing('user_tests', ['id' => $attemptMath->id]);

        // Verify the copied answer retains its original time_spent and created_at (not merge time)
        $copiedAnswer = \App\Models\UserTestAnswer::where('user_test_id', $result->id)
            ->where('question_id', $mathQuestion->id)
            ->first();
        $this->assertNotNull($copiedAnswer);
        $this->assertEquals(42, $copiedAnswer->time_spent);
        $this->assertTrue($copiedAnswer->created_at->equalTo($mathAnswer->created_at));
    }

    public function test_auto_merge_preserves_assignment_section_attempt()
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::create([
            'owner_id' => $teacher->id,
            'name' => 'Math Class',
            'code' => 'MATH101',
            'status' => 'active',
        ]);

        $test = Test::create([
            'title' => 'SAT Assignment Test',
            'test_type' => 'full_length',
            'status' => 'active',
        ]);

        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'assign_type' => 'section',
            'section_type' => 'math',
            'title' => 'Math Homework',
        ]);

        $attemptRw = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'attempt_type' => 'section',
            'section_type' => 'reading_writing',
            'status' => 'completed',
            'score_reading_writing' => 620,
            'completed_at' => now()->subHours(2),
        ]);

        $attemptMath = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'assignment_id' => $assignment->id,
            'attempt_type' => 'section',
            'section_type' => 'math',
            'status' => 'completed',
            'score_math' => 680,
            'completed_at' => now(),
        ]);

        $progression = app(\App\Services\TestProgressionService::class);
        $reflection = new \ReflectionClass($progression);
        $method = $reflection->getMethod('autoMergeIfEligible');
        $method->setAccessible(true);
        $result = $method->invoke($progression, $attemptMath, $test);

        // Merged result is Attempt RW
        $this->assertEquals($attemptRw->id, $result->id);
        $this->assertEquals(1300, $result->total_score);

        // Assignment attempt for Math MUST still exist in database for assignment report view
        $this->assertDatabaseHas('user_tests', [
            'id' => $attemptMath->id,
            'assignment_id' => $assignment->id,
            'score_math' => 680,
        ]);
    }
}
