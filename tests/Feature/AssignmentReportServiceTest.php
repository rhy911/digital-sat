<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\Classroom;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTestAnswer;
use App\Models\UserTest;
use App\Services\AssignmentReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createAssignment(array $attributes = [], ?User $teacher = null): array
    {
        $teacher ??= User::factory()->teacher()->create();

        $classroom = Classroom::create([
            'owner_id' => $teacher->id,
            'name' => 'Report Test Class',
            'code' => 'REP'.rand(1000, 9999),
            'status' => 'active',
        ]);

        $test = Test::create([
            'title' => 'SAT Practice Test',
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

        $q1 = Question::create([
            'stem' => 'RW Q1',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'reading_writing',
            'skill_domain' => 'craft_and_structure',
            'calculator_allowed' => false,
            'is_complete' => true,
            'created_by' => $teacher->id,
        ]);
        $rwModule->questions()->attach($q1->id, ['position' => 1]);

        $assignment = Assignment::create(array_merge([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'assign_type' => 'full',
            'title' => 'Sample Assignment',
            'attempt_limit' => 2,
            'status' => 'published',
        ], $attributes));

        return compact('teacher', 'classroom', 'test', 'assignment', 'rwModule', 'mathModule', 'q1');
    }

    private function createRecipient(Assignment $assignment, User $student, array $attributes = []): AssignmentRecipient
    {
        return AssignmentRecipient::create(array_merge([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'status' => 'active',
            'assigned_at' => now(),
        ], $attributes));
    }

    public function test_sorting_by_name_asc_and_desc(): void
    {
        extract($this->createAssignment());
        $service = app(AssignmentReportService::class);

        $s1 = User::factory()->student()->create(['name' => 'Charlie Brown']);
        $s2 = User::factory()->student()->create(['name' => 'Alice Adams']);
        $s3 = User::factory()->student()->create(['name' => 'Bob Clark']);

        foreach ([$s1, $s2, $s3] as $student) {
            $this->createRecipient($assignment, $student);
        }

        $asc = $service->build($assignment, perPage: 10, page: 1, sortField: 'name', sortDirection: 'asc');
        $ascNames = $asc['rows']->map(fn ($r) => $r['recipient']->student->name)->all();
        $this->assertSame(['Alice Adams', 'Bob Clark', 'Charlie Brown'], $ascNames);

        $desc = $service->build($assignment, perPage: 10, page: 1, sortField: 'name', sortDirection: 'desc');
        $descNames = $desc['rows']->map(fn ($r) => $r['recipient']->student->name)->all();
        $this->assertSame(['Charlie Brown', 'Bob Clark', 'Alice Adams'], $descNames);
    }

    public function test_sorting_by_score_with_ties_and_missing_scores(): void
    {
        extract($this->createAssignment());
        $service = app(AssignmentReportService::class);

        $sAlice = User::factory()->student()->create(['name' => 'Alice']);
        $sBob = User::factory()->student()->create(['name' => 'Bob']);
        $sCharlie = User::factory()->student()->create(['name' => 'Charlie']); // ties with Alice
        $sDave = User::factory()->student()->create(['name' => 'Dave']); // no attempts (missing score)

        foreach ([$sAlice, $sBob, $sCharlie, $sDave] as $student) {
            $this->createRecipient($assignment, $student);
        }

        // Bob has high score
        UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $sBob->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'total_score' => 1500,
            'score_reading_writing' => 750,
            'score_math' => 750,
            'completed_at' => now(),
        ]);

        // Alice and Charlie both have 1200 -> tie breaker should order by name 'Alice', then 'Charlie'
        UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $sAlice->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'total_score' => 1200,
            'score_reading_writing' => 600,
            'score_math' => 600,
            'completed_at' => now(),
        ]);

        UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $sCharlie->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'total_score' => 1200,
            'score_reading_writing' => 600,
            'score_math' => 600,
            'completed_at' => now(),
        ]);

        // Score desc: Bob (1500), Alice (1200), Charlie (1200), Dave (-1)
        $desc = $service->build($assignment, perPage: 10, page: 1, sortField: 'score', sortDirection: 'desc');
        $descNames = $desc['rows']->map(fn ($r) => $r['recipient']->student->name)->all();
        $this->assertSame(['Bob', 'Alice', 'Charlie', 'Dave'], $descNames);

        // Score asc: Dave (-1), Alice (1200), Charlie (1200), Bob (1500)
        $asc = $service->build($assignment, perPage: 10, page: 1, sortField: 'score', sortDirection: 'asc');
        $ascNames = $asc['rows']->map(fn ($r) => $r['recipient']->student->name)->all();
        $this->assertSame(['Dave', 'Alice', 'Charlie', 'Bob'], $ascNames);
    }

    public function test_sorting_by_rw_and_math_section_scores(): void
    {
        extract($this->createAssignment());
        $service = app(AssignmentReportService::class);

        $s1 = User::factory()->student()->create(['name' => 'Student 1']);
        $s2 = User::factory()->student()->create(['name' => 'Student 2']);

        foreach ([$s1, $s2] as $student) {
            $this->createRecipient($assignment, $student);
        }

        // Student 1 has high RW, lower Math
        UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $s1->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'total_score' => 1300,
            'score_reading_writing' => 750,
            'score_math' => 550,
            'completed_at' => now(),
        ]);

        // Student 2 has lower RW, higher Math
        UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $s2->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'total_score' => 1350,
            'score_reading_writing' => 600,
            'score_math' => 750,
            'completed_at' => now(),
        ]);

        // RW desc -> Student 1 first
        $rwDesc = $service->build($assignment, perPage: 10, page: 1, sortField: 'rw', sortDirection: 'desc');
        $this->assertSame('Student 1', $rwDesc['rows']->first()['recipient']->student->name);

        // Math desc -> Student 2 first
        $mathDesc = $service->build($assignment, perPage: 10, page: 1, sortField: 'math', sortDirection: 'desc');
        $this->assertSame('Student 2', $mathDesc['rows']->first()['recipient']->student->name);
    }

    public function test_sorting_section_assignment_uses_section_score(): void
    {
        extract($this->createAssignment(['assign_type' => 'section', 'section_type' => 'math']));
        $service = app(AssignmentReportService::class);

        $s1 = User::factory()->student()->create(['name' => 'Student 1']);
        $s2 = User::factory()->student()->create(['name' => 'Student 2']);

        foreach ([$s1, $s2] as $student) {
            $this->createRecipient($assignment, $student);
        }

        // Student 1 has higher total score but lower math score
        UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $s1->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'total_score' => 1400,
            'score_reading_writing' => 800,
            'score_math' => 600,
            'completed_at' => now(),
        ]);

        // Student 2 has lower total score but higher math score
        UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $s2->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'total_score' => 780,
            'score_reading_writing' => 0,
            'score_math' => 780,
            'completed_at' => now(),
        ]);

        // Sort score desc -> should rank by math score, so Student 2 comes before Student 1
        $report = $service->build($assignment, perPage: 10, page: 1, sortField: 'score', sortDirection: 'desc');
        $this->assertSame('Student 2', $report['rows']->first()['recipient']->student->name);
    }

    public function test_sorting_by_attempts_count(): void
    {
        extract($this->createAssignment());
        $service = app(AssignmentReportService::class);

        $s1 = User::factory()->student()->create(['name' => 'One Attempt']);
        $s2 = User::factory()->student()->create(['name' => 'Two Attempts']);
        $s3 = User::factory()->student()->create(['name' => 'Zero Attempts']);

        foreach ([$s1, $s2, $s3] as $student) {
            $this->createRecipient($assignment, $student);
        }

        UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $s1->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'total_score' => 1000,
            'completed_at' => now(),
        ]);

        UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $s2->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'total_score' => 1100,
            'completed_at' => now(),
        ]);
        UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $s2->id,
            'status' => 'completed',
            'attempt_number' => 2,
            'total_score' => 1200,
            'completed_at' => now(),
        ]);

        $desc = $service->build($assignment, perPage: 10, page: 1, sortField: 'attempts', sortDirection: 'desc');
        $names = $desc['rows']->map(fn ($r) => $r['recipient']->student->name)->all();
        $this->assertSame(['Two Attempts', 'One Attempt', 'Zero Attempts'], $names);

        $asc = $service->build($assignment, perPage: 10, page: 1, sortField: 'attempts', sortDirection: 'asc');
        $namesAsc = $asc['rows']->map(fn ($r) => $r['recipient']->student->name)->all();
        $this->assertSame(['Zero Attempts', 'One Attempt', 'Two Attempts'], $namesAsc);
    }

    public function test_status_resolution_with_withdrawn_and_bonus_attempts(): void
    {
        extract($this->createAssignment(['attempt_limit' => 1]));
        $service = app(AssignmentReportService::class);

        $sWithdrawn = User::factory()->student()->create(['name' => 'Withdrawn Student']);
        $sBonus = User::factory()->student()->create(['name' => 'Bonus Student']);

        $this->createRecipient($assignment, $sWithdrawn, ['status' => 'withdrawn']);
        $this->createRecipient($assignment, $sBonus, ['status' => 'active', 'extra_attempts' => 1]);

        // Bonus student completed 1 attempt (limit is 2, so should be 'Retry available')
        UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $sBonus->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'total_score' => 1100,
            'completed_at' => now(),
        ]);

        $report = $service->build($assignment, perPage: 10, page: 1, sortField: 'name', sortDirection: 'asc');
        $rows = $report['rows']->keyBy(fn ($r) => $r['recipient']->student->name);

        $this->assertSame('withdrawn', $rows['Withdrawn Student']['recipient']->status);
        $this->assertSame(1, $rows['Bonus Student']['completed_count']);
        $this->assertTrue($rows['Bonus Student']['recipient']->assignment->acceptsNewStarts());
    }

    public function test_pagination_only_hydrates_current_page_attempts_and_does_not_load_user_answers(): void
    {
        extract($this->createAssignment());
        $service = app(AssignmentReportService::class);

        // Create 20 students, each with an attempt and an answer
        $students = User::factory()->count(20)->student()->create();
        foreach ($students as $i => $student) {
            $this->createRecipient($assignment, $student);

            $attempt = UserTest::create([
                'assignment_id' => $assignment->id,
                'test_id' => $test->id,
                'user_id' => $student->id,
                'status' => 'completed',
                'attempt_number' => 1,
                'total_score' => 1000 + $i * 10,
                'completed_at' => now(),
            ]);

            UserTestAnswer::create([
                'user_test_id' => $attempt->id,
                'question_id' => $q1->id,
                'module_id' => $rwModule->id,
                'is_correct' => true,
            ]);
        }

        // Request page 1 with perPage = 15
        $report = $service->build($assignment, perPage: 15, page: 1, includeAnalysis: false);

        $this->assertCount(15, $report['rows']);
        $this->assertNotNull($report['paginator']);
        $this->assertSame(20, $report['paginator']->total());

        // Check each row in page 1
        foreach ($report['rows'] as $row) {
            $this->assertCount(1, $row['attempts']);
            $attempt = $row['attempts']->first();

            // Must NOT have hydrated full userAnswers relation
            $this->assertFalse(
                $attempt->relationLoaded('userAnswers'),
                'Attempt should NOT have loaded the userAnswers relation'
            );

            // Correctness count is loaded via withCount
            $this->assertSame(1, (int) $attempt->correct_answers_count);
            $this->assertSame(1, (int) $attempt->total_questions_count);
        }

        // Verify that off-page student attempts were NOT hydrated
        $page1StudentIds = $report['rows']->pluck('recipient.student_id')->all();
        $allHydratedStudentIds = $report['rows']->flatMap(fn ($r) => $r['attempts']->pluck('user_id'))->all();
        $this->assertEqualsCanonicalizing($page1StudentIds, $allHydratedStudentIds);
        $this->assertCount(15, $allHydratedStudentIds);
    }

    public function test_unpaginated_export_returns_all_rows(): void
    {
        extract($this->createAssignment());
        $service = app(AssignmentReportService::class);

        $students = User::factory()->count(18)->student()->create();
        foreach ($students as $student) {
            $this->createRecipient($assignment, $student);
        }

        // perPage = null (used by exports)
        $report = $service->build($assignment, perPage: null, includeAnalysis: false);
        $this->assertNull($report['paginator']);
        $this->assertCount(18, $report['rows']);
    }

    public function test_question_analysis_captures_complete_students_responses_and_incorrect_students(): void
    {
        extract($this->createAssignment());
        $service = app(AssignmentReportService::class);

        $s1 = User::factory()->student()->create(['name' => 'Correct Student']);
        $s2 = User::factory()->student()->create(['name' => 'Incorrect Student']);
        $s3 = User::factory()->student()->create(['name' => 'Omitted Student']);

        foreach ([$s1, $s2, $s3] as $student) {
            $this->createRecipient($assignment, $student);
        }

        // Student 1: Correct answer
        $att1 = UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $s1->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'completed_at' => now(),
        ]);
        UserTestAnswer::create([
            'user_test_id' => $att1->id,
            'question_id' => $q1->id,
            'module_id' => $rwModule->id,
            'is_correct' => true,
            'selected_answer' => 'B',
        ]);

        // Student 2: Incorrect answer
        $att2 = UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $s2->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'completed_at' => now(),
        ]);
        UserTestAnswer::create([
            'user_test_id' => $att2->id,
            'question_id' => $q1->id,
            'module_id' => $rwModule->id,
            'is_correct' => false,
            'selected_answer' => 'A',
        ]);

        // Student 3: Omitted answer
        $att3 = UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $test->id,
            'user_id' => $s3->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'completed_at' => now(),
        ]);
        UserTestAnswer::create([
            'user_test_id' => $att3->id,
            'question_id' => $q1->id,
            'module_id' => $rwModule->id,
            'is_correct' => false,
            'selected_answer' => null,
        ]);

        $report = $service->build($assignment, includeAnalysis: true);

        $this->assertNotEmpty($report['questionAnalysis']);
        $analysis = $report['questionAnalysis'][0];

        $this->assertSame(3, $analysis['total_presented']);
        $this->assertSame(1, $analysis['correct_count']);
        $this->assertCount(2, $analysis['incorrect_students']);
        $this->assertCount(3, $analysis['students']);

        $statuses = collect($analysis['students'])->pluck('status')->all();
        $this->assertContains('correct', $statuses);
        $this->assertContains('incorrect', $statuses);
        $this->assertContains('omitted', $statuses);
    }

    public function test_question_inspection_endpoint_scopes_questions_and_rejects_unrelated_questions(): void
    {
        ['assignment' => $assignment, 'teacher' => $teacher, 'q1' => $assignedQuestion] = $this->createAssignment();

        // An unrelated question belonging to another teacher's test
        $otherTeacher = User::factory()->teacher()->create();
        $unrelatedQuestion = Question::create([
            'stem' => 'Unrelated question stem',
            'question_type' => 'multiple_choice',
            'skill_domain' => 'craft_and_structure',
            'created_by' => $otherTeacher->id,
            'is_pretest' => false,
        ]);

        // Pretest question inside the same test
        $pretestQuestion = Question::create([
            'stem' => 'Pretest question stem',
            'question_type' => 'multiple_choice',
            'skill_domain' => 'craft_and_structure',
            'created_by' => $teacher->id,
            'is_pretest' => true,
        ]);
        $section = $assignment->test->sections()->first();
        $module = $section->modules()->first();
        $module->questions()->attach($pretestQuestion->id, ['position' => 99]);

        // 1. Authorized teacher inspects assigned question -> 200
        $response = $this->actingAs($teacher)->getJson(route('teacher.assignments.question-inspection', [
            'assignment' => $assignment,
            'question' => $assignedQuestion,
        ]));
        $response->assertOk();
        $response->assertJsonStructure(['stem', 'choices', 'students']);

        // 2. Unrelated question -> 404
        $unrelatedResponse = $this->actingAs($teacher)->getJson(route('teacher.assignments.question-inspection', [
            'assignment' => $assignment,
            'question' => $unrelatedQuestion,
        ]));
        $unrelatedResponse->assertNotFound();

        // 3. Pretest question belonging to test -> 200 (now inspectable with is_pretest flag)
        $pretestResponse = $this->actingAs($teacher)->getJson(route('teacher.assignments.question-inspection', [
            'assignment' => $assignment,
            'question' => $pretestQuestion,
        ]));
        $pretestResponse->assertOk();
        $this->assertTrue($pretestResponse->json('is_pretest'));
    }

    public function test_analysis_summary_supplies_safe_defaults_for_all_questions(): void
    {
        ['assignment' => $assignment, 'teacher' => $teacher, 'q1' => $assignedQuestion] = $this->createAssignment();
        $service = app(AssignmentReportService::class);

        // Test with a completed attempt so summary includes the question
        $student = User::factory()->student()->create();
        $attempt = UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $assignment->test_id,
            'user_id' => $student->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'completed_at' => now(),
        ]);
        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'question_id' => $assignedQuestion->id,
            'module_id' => $assignment->test->sections()->first()->modules()->first()->id,
            'is_correct' => true,
            'selected_answer' => 'A',
        ]);

        $summary = $service->buildAnalysisSummary($assignment);
        $this->assertNotEmpty($summary['questions']);

        $first = $summary['questions'][0];
        $this->assertIsArray($first['students']);
        $this->assertEmpty($first['students']);
        $this->assertIsArray($first['choices']);
        $this->assertEmpty($first['choices']);
        $this->assertFalse($first['details_loaded']);
        $this->assertNull($first['load_error']);
    }

    public function test_student_results_and_analysis_include_pretest_questions(): void
    {
        ['assignment' => $assignment, 'teacher' => $teacher, 'q1' => $opQuestion, 'rwModule' => $rwModule] = $this->createAssignment();
        $service = app(AssignmentReportService::class);

        // Create a pretest question attached to the same module
        $pretestQuestion = Question::create([
            'stem' => 'Pretest stem',
            'question_type' => 'multiple_choice',
            'skill_domain' => 'craft_and_structure',
            'created_by' => $teacher->id,
            'is_pretest' => true,
        ]);
        $rwModule->questions()->attach($pretestQuestion->id, ['position' => 2]);

        // Student completes both questions, getting both correct
        $student = User::factory()->student()->create();
        $this->createRecipient($assignment, $student);

        $attempt = UserTest::create([
            'assignment_id' => $assignment->id,
            'test_id' => $assignment->test_id,
            'user_id' => $student->id,
            'status' => 'completed',
            'attempt_number' => 1,
            'total_score' => 1200,
            'completed_at' => now(),
        ]);

        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'question_id' => $opQuestion->id,
            'module_id' => $rwModule->id,
            'is_correct' => true,
            'selected_answer' => 'A',
        ]);
        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'question_id' => $pretestQuestion->id,
            'module_id' => $rwModule->id,
            'is_correct' => true,
            'selected_answer' => 'B',
        ]);

        // 1. Report build: total_questions_count and correct_answers_count include pretest
        $report = $service->build($assignment, perPage: 15, page: 1, includeAnalysis: false);
        $bestAttempt = $report['rows'][0]['best'];
        $this->assertSame(2, (int) $bestAttempt->total_questions_count);
        $this->assertSame(2, (int) $bestAttempt->correct_answers_count);

        // 2. Question count for assignment includes pretest
        $this->assertSame(2, $service->getAssignmentQuestionCount($assignment));

        // 3. Question analysis summary includes pretest question with is_pretest = true
        $summary = $service->buildAnalysisSummary($assignment);
        $this->assertCount(2, $summary['questions']);
        $pretestItem = collect($summary['questions'])->firstWhere('id', $pretestQuestion->id);
        $this->assertNotNull($pretestItem);
        $this->assertTrue($pretestItem['is_pretest']);
        $this->assertSame(2, $summary['stats']['total']);
    }
}
