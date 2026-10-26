<?php

namespace Tests\Feature;

use App\Models\AnswerChoice;
use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\Classroom;
use App\Models\ClassroomMembership;
use App\Models\ExamSession;
use App\Models\Module;
use App\Models\Passage;
use App\Models\Question;
use App\Models\QuestionExplanation;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Services\ScoreReportService;
use App\Support\QuestionPacing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreReportOverhaulTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_owner_can_access_own_completed_score_report(): void
    {
        $student = User::factory()->student()->create();
        [$test, $userTest, $answer] = $this->createCompletedAttempt($student);

        $response = $this->actingAs($student)->get(route('student.scores.show', $userTest));

        $response->assertOk()
            ->assertSee($test->title)
            ->assertSee('Score overview')
            ->assertSee('id="overview"', false)
            ->assertSee('id="performance"', false)
            ->assertSee('id="review"', false);
    }

    public function test_student_can_export_pdf_for_completed_attempt(): void
    {
        $student = User::factory()->student()->create();
        [$test, $userTest] = $this->createCompletedAttempt($student);

        $response = $this->actingAs($student)->get(route('student.scores.export-pdf', $userTest));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_student_intruder_cannot_access_another_students_score_report(): void
    {
        $student = User::factory()->student()->create();
        $intruder = User::factory()->student()->create();
        [$test, $userTest] = $this->createCompletedAttempt($student);

        $response = $this->actingAs($intruder)->get(route('student.scores.show', $userTest));

        $response->assertForbidden();
    }

    public function test_teacher_score_history_scoped_strictly_to_authorized_classroom(): void
    {
        $teacher1 = User::factory()->teacher()->create();
        $teacher2 = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        // Classroom 1 owned by Teacher 1
        $classroom1 = Classroom::create(['owner_id' => $teacher1->id, 'name' => 'Cohort 1']);
        ClassroomMembership::create(['classroom_id' => $classroom1->id, 'student_id' => $student->id, 'status' => 'active']);

        // Classroom 2 owned by Teacher 2
        $classroom2 = Classroom::create(['owner_id' => $teacher2->id, 'name' => 'Cohort 2']);
        ClassroomMembership::create(['classroom_id' => $classroom2->id, 'student_id' => $student->id, 'status' => 'active']);

        // Attempt 1 for Classroom 1
        [$test1, $attempt1] = $this->createCompletedAttempt($student, 'Test Alpha');
        $assignment1 = Assignment::create(['classroom_id' => $classroom1->id, 'teacher_id' => $teacher1->id, 'test_id' => $test1->id, 'title' => 'Assigned Test 1']);
        $attempt1->update(['assignment_id' => $assignment1->id]);

        // Attempt 2 for Classroom 2
        [$test2, $attempt2] = $this->createCompletedAttempt($student, 'Test Beta');
        $assignment2 = Assignment::create(['classroom_id' => $classroom2->id, 'teacher_id' => $teacher2->id, 'test_id' => $test2->id, 'title' => 'Assigned Test 2']);
        $attempt2->update(['assignment_id' => $assignment2->id]);

        // Teacher 1 views Attempt 1: Should succeed and ONLY see Attempt 1 in history, NOT Attempt 2
        $response1 = $this->actingAs($teacher1)->get(route('student.scores.show', $attempt1));
        $response1->assertOk()
            ->assertSee('Test Alpha')
            ->assertDontSee('Test Beta');

        // Teacher 1 attempts to directly view Attempt 2: Should be forbidden
        $response2 = $this->actingAs($teacher1)->get(route('student.scores.show', $attempt2));
        $response2->assertForbidden();
    }

    public function test_student_accessing_incomplete_attempt_is_redirected_to_my_practice(): void
    {
        $student = User::factory()->student()->create();
        [$test, $incompleteTest] = $this->createIncompleteAttempt($student);

        $response = $this->actingAs($student)->get(route('student.scores.show', $incompleteTest));

        $response->assertRedirect(route('my-practice', $incompleteTest));
    }

    public function test_teacher_accessing_incomplete_attempt_receives_409_unavailable_without_answers_or_explanations(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::create(['owner_id' => $teacher->id, 'name' => 'Cohort']);
        ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'active']);

        [$test, $incompleteTest, $question] = $this->createIncompleteAttempt($student, 'Secret Test');
        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'teacher_id' => $teacher->id, 'test_id' => $test->id, 'title' => 'Secret Assignment']);
        $incompleteTest->update(['assignment_id' => $assignment->id]);

        $response = $this->actingAs($teacher)->get(route('student.scores.show', $incompleteTest));

        $response->assertStatus(409)
            ->assertSee('Score Report Unavailable')
            ->assertSee('in progress')
            ->assertSee('var(--ds-warning-strong', false)
            ->assertDontSee('#d97706')
            ->assertDontSee($question->stem)
            ->assertDontSee('Answer choices', false)
            ->assertDontSee('explanation', false);
    }

    public function test_export_pdf_on_incomplete_attempt_aborts_with_409_conflict(): void
    {
        $student = User::factory()->student()->create();
        [$test, $incompleteTest] = $this->createIncompleteAttempt($student);

        $response = $this->actingAs($student)->get(route('student.scores.export-pdf', $incompleteTest));

        $response->assertStatus(409);
    }

    public function test_answer_review_patch_on_incomplete_attempt_aborts_with_409_conflict(): void
    {
        $student = User::factory()->student()->create();
        [$test, $incompleteTest, $question, $answer] = $this->createIncompleteAttempt($student);

        $response = $this->actingAs($student)->patchJson(
            route('student.scores.answers.review', [$incompleteTest, $answer]),
            ['error_type' => 'careless']
        );

        $response->assertStatus(409);
    }

    public function test_score_report_payload_includes_passage_markdown(): void
    {
        $student = User::factory()->student()->create();
        $teacher = User::factory()->teacher()->create();

        $passage = Passage::create([
            'content' => "## The Great Gatsby\nIn my younger and more vulnerable years...",
            'passage_type' => 'single',
        ]);

        $test = Test::create(['title' => 'Passage Test', 'test_type' => 'full_length', 'created_by' => $teacher->id]);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Reading and Writing', 'type' => 'reading_writing', 'order' => 1, 'created_by' => $teacher->id]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 32, 'total_questions' => 1, 'order' => 1, 'created_by' => $teacher->id]);

        $question = Question::create([
            'passage_id' => $passage->id,
            'stem' => 'What is the tone of the narrator?',
            'question_type' => 'multiple_choice',
            'difficulty' => 'medium',
            'section_type' => 'reading_writing',
            'skill_domain' => 'information_and_ideas',
            'is_complete' => true,
            'created_by' => $teacher->id,
        ]);
        AnswerChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => 'Reflective', 'is_correct' => true, 'order' => 1]);
        $module->questions()->attach($question->id, ['position' => 1]);

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'attempt_type' => 'full',
            'status' => 'completed',
            'completed_at' => now(),
            'score_reading_writing' => 600,
            'score_math' => 600,
            'total_score' => 1200,
        ]);
        $answer = UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $question->id,
            'selected_answer' => 'A',
            'is_correct' => true,
            'time_spent' => 45,
        ]);

        $reportService = app(ScoreReportService::class);
        $reportData = $reportService->buildReportData($attempt->load([
            'test', 'user', 'userAnswers.module', 'userAnswers.question.passage',
            'userAnswers.question.explanation', 'userAnswers.question.answerChoices',
        ]));

        $this->assertArrayHasKey($answer->id, $reportData['questionDataMap']);
        $payload = $reportData['questionDataMap'][$answer->id];
        $this->assertNotNull($payload['passage']);
        $this->assertStringContainsString('<h2>The Great Gatsby</h2>', $payload['passage']);
        $this->assertStringContainsString('In my younger and more vulnerable years', $payload['passage']);

        // Check web response renders passage container and content
        $webResponse = $this->actingAs($student)->get(route('student.scores.show', $attempt));
        $webResponse->assertOk()
            ->assertSee('sd-modal-passage', false)
            ->assertSee('The Great Gatsby', false);
    }

    public function test_pacing_calculation_boundaries(): void
    {
        // 1. Missing or zero timeSpent -> 'No timing data'
        $noneNull = QuestionPacing::interpret(null, 60);
        $this->assertSame('none', $noneNull['status']);
        $this->assertSame('No timing data', $noneNull['label']);
        $this->assertNull($noneNull['actualSeconds']);
        $this->assertNull($noneNull['ratio']);

        $noneZero = QuestionPacing::interpret(0, 60);
        $this->assertSame('none', $noneZero['status']);
        $this->assertSame('No timing data', $noneZero['label']);

        $noneNegative = QuestionPacing::interpret(-5, 60);
        $this->assertSame('none', $noneNegative['status']);

        // 2. Positive timeSpent, null expected -> Recorded time
        $recordedOnly = QuestionPacing::interpret(45, null);
        $this->assertSame('expected', $recordedOnly['status']);
        $this->assertSame('Recorded time (45s)', $recordedOnly['label']);
        $this->assertSame('45s', $recordedOnly['shortLabel']);

        // 3. Ratio < 0.40 -> rushed (<40%)
        $rushed = QuestionPacing::interpret(39, 100);
        $this->assertSame('rushed', $rushed['status']);
        $this->assertSame('Potentially rushed (<40% expected)', $rushed['label']);
        $this->assertSame('Rushed', $rushed['shortLabel']);
        $this->assertSame(0.39, $rushed['ratio']);

        // 4. Boundary 0.40 -> expected
        $boundaryLow = QuestionPacing::interpret(40, 100);
        $this->assertSame('expected', $boundaryLow['status']);
        $this->assertSame('Expected pace (40–150%)', $boundaryLow['label']);
        $this->assertSame(0.4, $boundaryLow['ratio']);

        // 5. Boundary 1.50 -> expected
        $boundaryHigh = QuestionPacing::interpret(150, 100);
        $this->assertSame('expected', $boundaryHigh['status']);
        $this->assertSame('Expected pace (40–150%)', $boundaryHigh['label']);
        $this->assertSame(1.5, $boundaryHigh['ratio']);

        // 6. Ratio > 1.50 -> slow (>150%)
        $slow = QuestionPacing::interpret(151, 100);
        $this->assertSame('slow', $slow['status']);
        $this->assertSame('Possibly stuck/slow (>150% expected)', $slow['label']);
        $this->assertSame('Stuck / Slow', $slow['shortLabel']);
        $this->assertSame(1.51, $slow['ratio']);

        // 7. Estimated expected flag
        $estimated = QuestionPacing::interpret(50, 50, true);
        $this->assertTrue($estimated['isEstimatedExpected']);
    }

    public function test_pretest_and_unscored_questions_counted_in_diagnostics_without_claiming_scored(): void
    {
        $student = User::factory()->student()->create();
        $teacher = User::factory()->teacher()->create();

        $test = Test::create(['title' => 'Diagnostic Count Test', 'test_type' => 'full_length', 'created_by' => $teacher->id]);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => 'math', 'order' => 1, 'created_by' => $teacher->id]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 35, 'total_questions' => 3, 'order' => 1, 'created_by' => $teacher->id]);

        // Q1: scored, correct
        $q1 = Question::create(['stem' => 'Q1', 'question_type' => 'multiple_choice', 'difficulty' => 'easy', 'section_type' => 'math', 'skill_domain' => 'algebra', 'is_complete' => true, 'is_pretest' => false, 'created_by' => $teacher->id]);
        AnswerChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => '1', 'is_correct' => true, 'order' => 1]);
        $module->questions()->attach($q1->id, ['position' => 1]);

        // Q2: pretest (unscored), incorrect
        $q2 = Question::create(['stem' => 'Q2 Pretest', 'question_type' => 'multiple_choice', 'difficulty' => 'medium', 'section_type' => 'math', 'skill_domain' => 'advanced_math', 'is_complete' => true, 'is_pretest' => true, 'created_by' => $teacher->id]);
        AnswerChoice::create(['question_id' => $q2->id, 'label' => 'A', 'content' => '2', 'is_correct' => true, 'order' => 1]);
        $module->questions()->attach($q2->id, ['position' => 2]);

        // Q3: scored, omitted
        $q3 = Question::create(['stem' => 'Q3 Omitted', 'question_type' => 'multiple_choice', 'difficulty' => 'hard', 'section_type' => 'math', 'skill_domain' => 'geometry', 'is_complete' => true, 'is_pretest' => false, 'created_by' => $teacher->id]);
        AnswerChoice::create(['question_id' => $q3->id, 'label' => 'A', 'content' => '3', 'is_correct' => true, 'order' => 1]);
        $module->questions()->attach($q3->id, ['position' => 3]);

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'attempt_type' => 'full',
            'status' => 'completed',
            'completed_at' => now(),
            'total_score' => 1000,
            'score_reading_writing' => 500,
            'score_math' => 500,
        ]);

        $a1 = UserTestAnswer::create(['user_test_id' => $attempt->id, 'module_id' => $module->id, 'question_id' => $q1->id, 'selected_answer' => 'A', 'is_correct' => true, 'time_spent' => 40]);
        $a2 = UserTestAnswer::create(['user_test_id' => $attempt->id, 'module_id' => $module->id, 'question_id' => $q2->id, 'selected_answer' => 'B', 'is_correct' => false, 'time_spent' => 50]);
        $a3 = UserTestAnswer::create(['user_test_id' => $attempt->id, 'module_id' => $module->id, 'question_id' => $q3->id, 'selected_answer' => null, 'is_correct' => false, 'time_spent' => 0]);

        $reportService = app(ScoreReportService::class);
        $reportData = $reportService->buildReportData($attempt->load([
            'test', 'user', 'userAnswers.module', 'userAnswers.question.answerChoices',
        ]));

        $this->assertSame(3, $reportData['totalQ']);
        $this->assertSame(1, $reportData['correct']);
        $this->assertSame(1, $reportData['wrong']);
        $this->assertSame(1, $reportData['omitted']);
        $this->assertSame(1, $reportData['unscored']);
        $this->assertSame(1, $reportData['mUnscored']);
        $this->assertSame(33, $reportData['accuracyPercent']);

        $this->assertTrue($reportData['questionDataMap'][$a2->id]['is_pretest']);
        $this->assertFalse($reportData['questionDataMap'][$a1->id]['is_pretest']);

        $response = $this->actingAs($student)->get(route('student.scores.show', $attempt));
        $response->assertOk()
            ->assertSee('All practice questions')
            ->assertSee('including 1 unscored question')
            ->assertSee('aria-label="Unscored question"', false);
    }

    public function test_student_html_spr_answer_is_safely_rendered_as_plain_text(): void
    {
        $student = User::factory()->student()->create();
        $teacher = User::factory()->teacher()->create();

        $test = Test::create(['title' => 'XSS Test', 'test_type' => 'full_length', 'created_by' => $teacher->id]);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => 'math', 'order' => 1, 'created_by' => $teacher->id]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 35, 'total_questions' => 1, 'order' => 1, 'created_by' => $teacher->id]);

        $question = Question::create([
            'stem' => 'Compute the value.',
            'question_type' => 'student_produced_response',
            'difficulty' => 'easy',
            'section_type' => 'math',
            'skill_domain' => 'algebra',
            'is_complete' => true,
            'created_by' => $teacher->id,
        ]);
        $module->questions()->attach($question->id, ['position' => 1]);

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'attempt_type' => 'full',
            'status' => 'completed',
            'completed_at' => now(),
            'total_score' => 1000,
            'score_reading_writing' => 500,
            'score_math' => 500,
        ]);

        $payload = '<script>alert("xss")</script><b>42</b>';
        $answer = UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $question->id,
            'selected_answer' => $payload,
            'is_correct' => false,
            'time_spent' => 20,
        ]);

        $response = $this->actingAs($student)->get(route('student.scores.show', $attempt));
        $response->assertOk();

        // Ensure raw unescaped script tag is not present in the HTML outside JSON or encoded text
        $content = $response->getContent();
        $this->assertStringNotContainsString('<script>alert("xss")</script><b>42</b>', $content);
        $this->assertStringContainsString(e($payload), $content);
    }

    public function test_score_report_view_does_not_contain_mastery_claims_or_meter_segments(): void
    {
        $student = User::factory()->student()->create();
        [$test, $userTest] = $this->createCompletedAttempt($student);

        $response = $this->actingAs($student)->get(route('student.scores.show', $userTest));
        $response->assertOk();

        $content = $response->getContent();

        // 7-segment meters eliminated
        $this->assertStringNotContainsString('meter-segment', $content);

        // Subjective mastery claims eliminated
        $this->assertStringNotContainsString('Needs Focus', $content);
        $this->assertStringNotContainsString('High Mastery', $content);
        $this->assertStringNotContainsString('Medium Mastery', $content);
        $this->assertStringNotContainsString('Low Mastery', $content);

        // Proportional accuracy bar present
        $this->assertStringContainsString('sd-accuracy-bar', $content);

        // Accessible dialog present with aria attributes
        $this->assertStringContainsString('role="dialog"', $content);
        $this->assertStringContainsString('aria-modal="true"', $content);
        $this->assertStringContainsString('aria-labelledby="modalQuestionTitle"', $content);
    }

    public function test_historical_snapshot_preserves_question_content_and_passage_after_test_edits(): void
    {
        $student = User::factory()->student()->create();
        $teacher = User::factory()->teacher()->create();

        $passage = Passage::create([
            'title' => 'Original Passage',
            'content' => 'Original passage text before edit.',
            'passage_type' => 'single',
        ]);
        $test = Test::create(['title' => 'Snapshot Test', 'test_type' => 'full_length', 'created_by' => $teacher->id]);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Reading and Writing', 'type' => 'reading_writing', 'order' => 1, 'created_by' => $teacher->id]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 32, 'total_questions' => 1, 'order' => 1, 'created_by' => $teacher->id]);

        $question = Question::create([
            'passage_id' => $passage->id,
            'stem' => 'Live question stem',
            'question_type' => 'multiple_choice',
            'difficulty' => 'medium',
            'section_type' => 'reading_writing',
            'skill_domain' => 'craft_and_structure',
            'is_complete' => true,
            'created_by' => $teacher->id,
        ]);
        AnswerChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => 'Live choice A', 'is_correct' => true, 'order' => 1]);
        $module->questions()->attach($question->id, ['position' => 1]);

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'attempt_type' => 'full',
            'status' => 'completed',
            'completed_at' => now(),
            'score_reading_writing' => 650,
            'score_math' => 650,
            'total_score' => 1300,
        ]);

        $answer = UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $question->id,
            'selected_answer' => 'A',
            'is_correct' => true,
            'time_spent' => 40,
            'question_snapshot' => [
                'stem' => 'Historical snapshot stem text',
                'choices' => [
                    ['label' => 'A', 'content' => 'Historical Choice A', 'is_correct' => true],
                    ['label' => 'B', 'content' => 'Historical Choice B', 'is_correct' => false],
                ],
                'passage' => [
                    'title' => 'Historical Passage',
                    'content' => 'Historical passage body preserved in snapshot.',
                ],
                'module_position' => 14,
                'is_pretest' => false,
            ],
        ]);

        // Modify live question and passage in DB
        $passage->update(['content' => 'MODIFIED live passage that should NOT leak into old report']);
        $question->update(['stem' => 'MODIFIED live stem that should NOT leak into old report']);

        $reportService = app(ScoreReportService::class);
        $reportData = $reportService->buildReportData($attempt->load([
            'test', 'user', 'userAnswers.module', 'userAnswers.question.passage',
            'userAnswers.question.explanation', 'userAnswers.question.answerChoices',
        ]));

        $row = $reportData['allAnswers'][0];
        $this->assertSame(14, $row['idx']);
        $this->assertStringContainsString('Historical snapshot stem text', $row['questionData']['stem']);

        $payload = $reportData['questionDataMap'][$answer->id];
        $this->assertStringContainsString('Historical passage body preserved in snapshot', $payload['passage']);
        $this->assertStringNotContainsString('MODIFIED live passage', $payload['passage']);

        $webResponse = $this->actingAs($student)->get(route('student.scores.show', $attempt));
        $webResponse->assertOk()
            ->assertSee('Historical passage body preserved in snapshot')
            ->assertDontSee('MODIFIED live passage');
    }

    public function test_unscaled_section_attempt_truthfully_displays_raw_score_instead_of_unscaled_total(): void
    {
        $student = User::factory()->student()->create();
        $teacher = User::factory()->teacher()->create();

        $test = Test::create(['title' => 'Single Section Quiz', 'test_type' => 'section_only', 'created_by' => $teacher->id]);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => 'math', 'order' => 1, 'created_by' => $teacher->id]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 20, 'total_questions' => 2, 'order' => 1, 'created_by' => $teacher->id]);

        $q1 = Question::create(['stem' => 'Math 1', 'question_type' => 'multiple_choice', 'difficulty' => 'easy', 'section_type' => 'math', 'skill_domain' => 'algebra', 'is_complete' => true, 'created_by' => $teacher->id]);
        AnswerChoice::create(['question_id' => $q1->id, 'label' => 'A', 'content' => '1', 'is_correct' => true, 'order' => 1]);
        $module->questions()->attach($q1->id, ['position' => 1]);

        $q2 = Question::create(['stem' => 'Math 2', 'question_type' => 'multiple_choice', 'difficulty' => 'easy', 'section_type' => 'math', 'skill_domain' => 'algebra', 'is_complete' => true, 'created_by' => $teacher->id]);
        AnswerChoice::create(['question_id' => $q2->id, 'label' => 'A', 'content' => '2', 'is_correct' => true, 'order' => 1]);
        $module->questions()->attach($q2->id, ['position' => 2]);

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'attempt_type' => 'section',
            'status' => 'completed',
            'completed_at' => now(),
            'total_score' => null,
            'score_reading_writing' => null,
            'score_math' => null,
        ]);

        UserTestAnswer::create(['user_test_id' => $attempt->id, 'module_id' => $module->id, 'question_id' => $q1->id, 'selected_answer' => 'A', 'is_correct' => true, 'time_spent' => 20]);
        UserTestAnswer::create(['user_test_id' => $attempt->id, 'module_id' => $module->id, 'question_id' => $q2->id, 'selected_answer' => 'B', 'is_correct' => false, 'time_spent' => 25]);

        $reportService = app(ScoreReportService::class);
        $reportData = $reportService->buildReportData($attempt->load([
            'test', 'user', 'userAnswers.module', 'userAnswers.question.answerChoices',
        ]));

        $presentation = $reportData['scorePresentation'];
        $this->assertFalse($presentation['isScaledSatResult']);
        $this->assertSame(1, $presentation['score']);
        $this->assertSame(2, $presentation['maxScore']);
        $this->assertSame('Raw question accuracy', $presentation['basisLabel']);

        $response = $this->actingAs($student)->get(route('student.scores.show', $attempt));
        $response->assertOk()
            ->assertSee('Raw question accuracy')
            ->assertDontSee('/ 800');
    }

    public function test_score_report_and_pdf_render_safely_when_attempt_has_no_assignment(): void
    {
        $student = User::factory()->student()->create();
        [$test, $attempt] = $this->createCompletedAttempt($student);

        // Ensure attempt is self-paced practice without assignment
        $attempt->update(['assignment_id' => null, 'exam_session_id' => null]);

        $response = $this->actingAs($student)->get(route('student.scores.show', $attempt));
        $response->assertOk()
            ->assertSee('Self-Paced Practice');

        $pdfResponse = $this->actingAs($student)->get(route('student.scores.export-pdf', $attempt));
        $pdfResponse->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_exam_session_metadata_is_displayed_in_report_and_pdf(): void
    {
        $student = User::factory()->student()->create();
        $teacher = User::factory()->teacher()->create();
        [$test, $attempt] = $this->createCompletedAttempt($student);

        $session = ExamSession::create([
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Spring 2026 Proctored Exam',
            'status' => ExamSession::STATUS_CLOSED,
        ]);
        $attempt->update(['exam_session_id' => $session->id]);

        $response = $this->actingAs($student)->get(route('student.scores.show', $attempt));
        $response->assertOk()
            ->assertSee('Spring 2026 Proctored Exam');

        $pdfResponse = $this->actingAs($student)->get(route('student.scores.export-pdf', $attempt));
        $pdfResponse->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_teacher_view_progress_links_to_classroom_ulid(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::create(['owner_id' => $teacher->id, 'name' => 'Cohort ULID Test']);
        ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'active']);

        [$test, $attempt] = $this->createCompletedAttempt($student);
        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'teacher_id' => $teacher->id, 'test_id' => $test->id, 'title' => 'Assigned Test']);
        $attempt->update(['assignment_id' => $assignment->id]);

        $response = $this->actingAs($teacher)->get(route('student.scores.show', $attempt));
        $response->assertOk()
            ->assertSee(route('teacher.classes.students.progress', ['classroom' => $classroom, 'student' => $student->id]), false);
    }

    public function test_abandoned_attempt_redirects_to_home_practice_breaking_loops(): void
    {
        $student = User::factory()->student()->create();
        [$test, $attempt] = $this->createIncompleteAttempt($student);
        $attempt->update(['status' => 'abandoned']);

        $response = $this->actingAs($student)->get(route('student.scores.show', $attempt));
        $response->assertRedirect(route('home.practice'));

        $practiceResponse = $this->actingAs($student)->get(route('my-practice', $attempt));
        $practiceResponse->assertRedirect(route('home.practice'));
    }

    public function test_explicit_null_explanation_in_snapshot_is_preserved_without_database_fallback(): void
    {
        $student = User::factory()->student()->create();
        [$test, $attempt, $answer] = $this->createCompletedAttempt($student);

        // Create a live database explanation on the underlying question
        QuestionExplanation::create([
            'question_id' => $answer->question_id,
            'explanation' => 'Live Database Explanation Text',
        ]);

        // Case 1: Snapshot explicitly has 'explanation' => null.
        // It must NOT fall back to the live database explanation.
        $answer->update([
            'question_snapshot' => [
                'stem' => 'Snapshot stem',
                'explanation' => null,
                'choices' => [
                    ['label' => 'A', 'content' => 'Choice A', 'is_correct' => true],
                ],
            ],
        ]);

        $reportService = app(ScoreReportService::class);
        $reportData = $reportService->buildReportData($attempt->fresh(['test', 'user', 'userAnswers.module']));
        $this->assertNull($reportData['questionDataMap'][$answer->id]['explanation']);

        // Case 2: Legacy snapshot without the 'explanation' key at all.
        // It DOES fall back to the live database explanation.
        $answer->update([
            'question_snapshot' => [
                'stem' => 'Legacy snapshot stem without explanation key',
                'choices' => [
                    ['label' => 'A', 'content' => 'Choice A', 'is_correct' => true],
                ],
            ],
        ]);

        $reportDataLegacy = $reportService->buildReportData($attempt->fresh(['test', 'user', 'userAnswers.module']));
        $this->assertNotNull($reportDataLegacy['questionDataMap'][$answer->id]['explanation']);
        $this->assertStringContainsString('Live Database Explanation Text', $reportDataLegacy['questionDataMap'][$answer->id]['explanation']);
    }

    public function test_legacy_generic_and_unknown_conversion_truthful_disclosures(): void
    {
        $student = User::factory()->student()->create();
        [$test, $attempt] = $this->createCompletedAttempt($student);

        // 1. generic_ds_v1 attempt
        $attempt->update([
            'score_estimate_kind' => 'generic_ds_v1',
            'score_conversion_version' => 'generic_ds_v1',
            'score_conversion_set_id' => null,
        ]);

        $reportService = app(ScoreReportService::class);
        $data1 = $reportService->buildReportData($attempt->fresh(['test', 'user', 'userAnswers.module']));
        $this->assertStringContainsString('Legacy standard conversion', $data1['scorePresentation']['basisLabel']);
        $this->assertStringNotContainsString('Official form conversion', $data1['scorePresentation']['basisLabel']);
        $this->assertStringContainsString('legacy scoring table', $data1['scorePresentation']['scoreDisclosure']);

        $response1 = $this->actingAs($student)->get(route('student.scores.show', $attempt));
        $response1->assertOk()
            ->assertSee('Legacy standard conversion')
            ->assertDontSee('Official form conversion');

        // 2. null / unknown legacy kind
        $attempt->update([
            'score_estimate_kind' => null,
            'score_conversion_version' => null,
            'score_conversion_set_id' => null,
        ]);

        $data2 = $reportService->buildReportData($attempt->fresh(['test', 'user', 'userAnswers.module']));
        $this->assertSame('Legacy conversion', $data2['scorePresentation']['basisLabel']);
        $this->assertStringNotContainsString('Official form conversion', $data2['scorePresentation']['basisLabel']);
        $this->assertStringContainsString('legacy scoring conversion', $data2['scorePresentation']['calculationDisclosure']);

        $response2 = $this->actingAs($student)->get(route('student.scores.show', $attempt));
        $response2->assertOk()
            ->assertSee('Legacy conversion')
            ->assertDontSee('Official form conversion');
    }

    public function test_export_pdf_does_not_contain_per_question_answer_table(): void
    {
        $student = User::factory()->student()->create();
        [$test, $attempt] = $this->createCompletedAttempt($student);

        $html = view('student.scores.export-pdf', app(ScoreReportService::class)->buildReportData($attempt))->render();
        $this->assertStringNotContainsString('Question-by-Question', $html);
        $this->assertStringNotContainsString('Question Summary', $html);
    }

    public function test_adaptive_full_and_section_truthful_provisional_range_disclosures(): void
    {
        $student = User::factory()->student()->create();
        $reportService = app(ScoreReportService::class);

        // 1. Adaptive Full Test
        [$testFull, $attemptFull] = $this->createCompletedAttempt($student, 'Adaptive Full');
        $testFull->update(['test_type' => Test::TYPE_ADAPTIVE_FULL]);
        $attemptFull->update([
            'attempt_type' => 'full',
            'rw_m2_path' => 'hard',
            'math_m2_path' => 'hard',
            'score_estimate_kind' => 'adaptive_irt_provisional',
            'total_score' => 1240,
            'total_score_lower' => 1170,
            'total_score_upper' => 1310,
        ]);

        $dataFull = $reportService->buildReportData($attemptFull->fresh(['test', 'user', 'userAnswers.module']));
        $this->assertSame('1170 – 1310', $dataFull['scorePresentation']['rangeFormatted']);
        $this->assertStringContainsString('between 1170 and 1310.', $dataFull['scorePresentation']['scoreDisclosure']);
        $this->assertStringContainsString('This test used an adaptive route', $dataFull['scorePresentation']['calculationDisclosure']);

        // 2. Adaptive Section Only (Math)
        [$testSec, $attemptSec] = $this->createCompletedAttempt($student, 'Adaptive Math Section');
        $testSec->update(['test_type' => Test::TYPE_ADAPTIVE_FULL]);
        $attemptSec->update([
            'attempt_type' => 'section',
            'section_type' => 'math',
            'math_m2_path' => 'easy',
            'score_estimate_kind' => 'adaptive_irt_provisional',
            'score_math' => 620,
            'score_math_lower' => 580,
            'score_math_upper' => 660,
        ]);

        $dataSec = $reportService->buildReportData($attemptSec->fresh(['test', 'user', 'userAnswers.module']));
        $this->assertSame('580 – 660', $dataSec['scorePresentation']['rangeFormatted']);
        $this->assertStringContainsString('between 580 and 660.', $dataSec['scorePresentation']['scoreDisclosure']);
        $this->assertStringContainsString('This section used an adaptive route', $dataSec['scorePresentation']['calculationDisclosure']);
    }

    public function test_irt_report_does_not_claim_adaptive_routing_without_a_recorded_route(): void
    {
        $student = User::factory()->student()->create();
        [, $attempt] = $this->createCompletedAttempt($student, 'Linear IRT Test');
        $attempt->update([
            'attempt_type' => 'full',
            'score_estimate_kind' => 'adaptive_irt_provisional',
            'total_score' => 1200,
            'total_score_lower' => 1150,
            'total_score_upper' => 1250,
            'rw_m2_path' => null,
            'math_m2_path' => null,
        ]);

        $presentation = app(ScoreReportService::class)
            ->buildReportData($attempt->fresh(['test', 'user', 'userAnswers.module']))['scorePresentation'];

        $this->assertSame('IRT provisional estimate', $presentation['basisLabel']);
        $this->assertStringContainsString('between 1150 and 1250', $presentation['scoreDisclosure']);
        $this->assertStringContainsString('EAP 3PL', $presentation['calculationDisclosure']);
        $this->assertStringNotContainsString('adaptive route', $presentation['calculationDisclosure']);
    }

    public function test_irt_attempt_with_partial_or_missing_bounds_gracefully_omits_range_text(): void
    {
        $student = User::factory()->student()->create();
        $reportService = app(ScoreReportService::class);

        [$test, $attempt] = $this->createCompletedAttempt($student, 'Adaptive Partial Bounds');
        $attempt->update([
            'attempt_type' => 'full',
            'score_estimate_kind' => 'adaptive_irt_provisional',
            'total_score' => 1200,
            'total_score_lower' => 1150,
            'total_score_upper' => null, // partial bound
        ]);

        $data = $reportService->buildReportData($attempt->fresh(['test', 'user', 'userAnswers.module']));
        $this->assertNull($data['scorePresentation']['rangeFormatted']);
        $this->assertSame('This is an estimate based on the questions you saw.', $data['scorePresentation']['scoreDisclosure']);
        $this->assertStringNotContainsString('between', $data['scorePresentation']['scoreDisclosure']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createCompletedAttempt(User $student, string $title = 'Full Mock SAT'): array
    {
        $teacher = User::factory()->teacher()->create();
        $test = Test::create(['title' => $title, 'test_type' => 'full_length', 'created_by' => $teacher->id]);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => 'math', 'order' => 1, 'created_by' => $teacher->id]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 35, 'total_questions' => 1, 'order' => 1, 'created_by' => $teacher->id]);
        $question = Question::create(['stem' => 'Solve 2x = 4.', 'question_type' => 'multiple_choice', 'difficulty' => 'easy', 'section_type' => 'math', 'skill_domain' => 'algebra', 'is_complete' => true, 'created_by' => $teacher->id]);
        AnswerChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => '2', 'is_correct' => true, 'order' => 1]);
        $module->questions()->attach($question->id, ['position' => 1]);

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'attempt_type' => 'full',
            'status' => 'completed',
            'completed_at' => now(),
            'score_reading_writing' => 600,
            'score_math' => 650,
            'total_score' => 1250,
        ]);

        $answer = UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $question->id,
            'selected_answer' => 'A',
            'is_correct' => true,
            'time_spent' => 45,
        ]);

        return [$test, $attempt, $answer];
    }

    private function createIncompleteAttempt(User $student, string $title = 'Incomplete SAT'): array
    {
        $teacher = User::factory()->teacher()->create();
        $test = Test::create(['title' => $title, 'test_type' => 'full_length', 'created_by' => $teacher->id]);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => 'math', 'order' => 1, 'created_by' => $teacher->id]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 35, 'total_questions' => 1, 'order' => 1, 'created_by' => $teacher->id]);
        $question = Question::create(['stem' => 'In progress question stem', 'question_type' => 'multiple_choice', 'difficulty' => 'medium', 'section_type' => 'math', 'skill_domain' => 'algebra', 'is_complete' => true, 'created_by' => $teacher->id]);
        AnswerChoice::create(['question_id' => $question->id, 'label' => 'A', 'content' => 'Answer Choice A', 'is_correct' => true, 'order' => 1]);
        $module->questions()->attach($question->id, ['position' => 1]);

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'attempt_type' => 'full',
            'status' => 'in_progress',
        ]);

        $answer = UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $question->id,
            'selected_answer' => 'B',
            'is_correct' => false,
            'time_spent' => 20,
        ]);

        return [$test, $attempt, $question, $answer];
    }

    public function test_partial_legacy_snapshot_inherits_live_metadata_for_absent_keys(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $test = Test::create(['title' => 'Snapshot Test', 'test_type' => 'full_length', 'created_by' => $teacher->id]);
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => 'math', 'order' => 1, 'created_by' => $teacher->id]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 35, 'total_questions' => 1, 'order' => 1, 'created_by' => $teacher->id]);
        $question = Question::create([
            'stem' => 'Live question stem',
            'question_type' => 'multiple_choice',
            'difficulty' => 'hard',
            'section_type' => 'math',
            'skill_domain' => 'advanced_math',
            'expected_time' => 90,
            'is_complete' => true,
            'created_by' => $teacher->id,
        ]);
        $module->questions()->attach($question->id, ['position' => 1]);

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'attempt_type' => 'full',
            'status' => 'completed',
            'completed_at' => now(),
            'total_score' => 1200,
        ]);

        // 1. Partial/legacy snapshot with only stem
        $answer = UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $question->id,
            'selected_answer' => 'A',
            'is_correct' => true,
            'time_spent' => 45,
            'question_snapshot' => ['stem' => 'Legacy snapshot stem'],
        ]);

        $hydrated = $answer->fresh()->question;
        $this->assertSame('Legacy snapshot stem', $hydrated->stem);
        $this->assertSame('math', $hydrated->section_type);
        $this->assertSame('hard', $hydrated->difficulty);
        $this->assertSame(90, $hydrated->expected_time);
        $this->assertSame('advanced_math', $hydrated->skill_domain);

        // 2. Explicit snapshot values must be preserved (even when null)
        $answer->update([
            'question_snapshot' => [
                'stem' => 'Explicit snapshot stem',
                'difficulty' => 'easy',
                'expected_time' => null,
            ],
        ]);

        $hydratedExplicit = $answer->fresh()->question;
        $this->assertSame('Explicit snapshot stem', $hydratedExplicit->stem);
        $this->assertSame('easy', $hydratedExplicit->difficulty);
        $this->assertNull($hydratedExplicit->expected_time);
        $this->assertSame('math', $hydratedExplicit->section_type); // still falls back since absent
    }

    public function test_score_report_anchor_nav_does_not_declare_tablist_and_pacing_indicators_have_distinct_marks(): void
    {
        $student = User::factory()->student()->create();
        [$test, $attempt] = $this->createCompletedAttempt($student);

        $response = $this->actingAs($student)->get(route('student.scores.show', $attempt));
        $response->assertOk();

        // Must not declare an incomplete tablist widget
        $response->assertSee('sd-anchor-nav');
        $response->assertDontSee('role="tablist"');

        // Multi-modal pacing indicator marks must be present
        $response->assertSee('sd-pace-mark expected');
        $response->assertSee('sd-pace-mark none');
    }

    public function test_corrupted_or_non_array_snapshot_safely_falls_back_to_live_question(): void
    {
        $student = User::factory()->student()->create();
        [$test, $attempt, $question, $answer] = $this->createIncompleteAttempt($student);

        // When question_snapshot is a non-array or invalid json
        $answer->update(['question_snapshot' => 'not-a-valid-json-array']);

        $hydrated = $answer->fresh()->question;
        $this->assertNotNull($hydrated);
        $this->assertSame($question->id, $hydrated->id);
        $this->assertSame($question->stem, $hydrated->stem);
    }

    public function test_complete_snapshot_with_null_expected_time_triggers_zero_live_queries(): void
    {
        $student = User::factory()->student()->create();
        [$test, $attempt, $answer] = $this->createCompletedAttempt($student);

        $completeSnapshot = [
            'stem' => 'Complete Snapshot Stem',
            'question_type' => 'multiple_choice',
            'difficulty' => 'hard',
            'skill_domain' => 'algebra',
            'skill_subdomain' => 'linear_equations',
            'expected_time' => null,
            'module_position' => 1,
            'is_pretest' => false,
            'calculator_allowed' => true,
            'irt_a' => 1.0,
            'irt_b' => 0.5,
            'irt_c' => 0.2,
            'irt_calibration_status' => 'calibrated',
            'irt_calibration_version' => 'v1',
            'section_type' => 'math',
            'passage' => null,
            'choices' => [
                ['label' => 'A', 'content' => '2', 'is_correct' => true, 'order' => 1],
            ],
            'spr_answers' => [],
            'explanation' => null,
        ];

        $answer->update(['question_snapshot' => $completeSnapshot]);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();

        $freshAnswer = $answer->fresh();
        \Illuminate\Support\Facades\DB::flushQueryLog();

        $q1 = $freshAnswer->question;
        $q2 = $freshAnswer->question;

        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        $this->assertCount(0, $queries, 'Complete snapshot must not execute live question database queries.');
        $this->assertSame('Complete Snapshot Stem', $q1->stem);
        $this->assertNull($q1->expected_time);

        // Incomplete snapshot: absent key executes exactly 1 query to load the live question, then reuses cached relation
        $incompleteSnapshot = [
            'stem' => 'Incomplete Snapshot Stem',
            // missing section_type, expected_time, etc.
            'passage' => null,
            'choices' => [],
            'spr_answers' => [],
            'explanation' => null,
        ];
        $freshAnswer2 = $answer->fresh();
        $freshAnswer2->question_snapshot = $incompleteSnapshot;

        \Illuminate\Support\Facades\DB::flushQueryLog();
        $qIncomplete1 = $freshAnswer2->question;
        $firstAccessQueries = \Illuminate\Support\Facades\DB::getQueryLog();
        $this->assertCount(1, $firstAccessQueries, 'First access with missing keys executes exactly 1 question lookup.');

        \Illuminate\Support\Facades\DB::flushQueryLog();
        $qIncomplete2 = $freshAnswer2->question;
        $secondAccessQueries = \Illuminate\Support\Facades\DB::getQueryLog();
        $this->assertCount(0, $secondAccessQueries, 'Second access reuses loaded relation without additional queries.');
    }

    public function test_overhauled_score_card_renders_3_zone_structure_for_scaled_full_test(): void
    {
        $student = User::factory()->student()->create();
        [$test, $attempt] = $this->createCompletedAttempt($student, 'SAT Practice Test 1');

        $attempt->update([
            'total_score' => 1280,
            'total_score_lower' => 1220,
            'total_score_upper' => 1340,
            'score_reading_writing' => 620,
            'score_reading_writing_lower' => 590,
            'score_reading_writing_upper' => 650,
            'score_math' => 660,
            'score_math_lower' => 630,
            'score_math_upper' => 690,
            'score_estimate_kind' => 'adaptive_irt_provisional',
        ]);

        $response = $this->actingAs($student)->get(route('student.scores.show', $attempt));
        $response->assertOk();

        // 1. Theme surface and header bar
        $response->assertSee('sd-paper-card sd-score-hero', false);
        $response->assertSee('sd-score-header-bar', false);
        $response->assertSee('sd-score-context-badge', false);
        $response->assertSee('Estimated practice score');
        $response->assertSee('sd-basis-badge', false);
        $response->assertSee('IRT provisional estimate');

        // 2. Explainer popover & actions
        $response->assertSee('sd-score-explainer-popover', false);
        $response->assertSee('sd-explainer-trigger', false);
        $response->assertSee('How is this calculated?');
        $response->assertSee('sd-overview-actions', false);
        $response->assertSee('id="btn-goto-review"', false);
        $response->assertSee('Review questions');
        $response->assertSee('Download summary PDF');

        // 3. Zone 1 (Total score)
        $response->assertSee('sd-metrics-grid', false);
        $response->assertSee('sd-metric-zone-total', false);
        $response->assertSee('1280');
        $response->assertSee('/ 1600');
        $response->assertSee('Score band:');
        $response->assertSee('1220 – 1340');

        // 4. Zone 2 & 3 (Micro-cards)
        $response->assertSee('sd-micro-card-rw', false);
        $response->assertSee('Reading &amp; Writing', false);
        $response->assertSee('620');
        $response->assertSee('590 – 650');

        $response->assertSee('sd-micro-card-math', false);
        $response->assertSee('Math');
        $response->assertSee('660');
        $response->assertSee('630 – 690');

        // 5. Disclosures at card base
        $response->assertSee('sd-score-disclosure', false);
        $response->assertSee('Not an official College Board score.');
    }

    public function test_overhauled_score_card_renders_single_zone_for_section_attempt(): void
    {
        $student = User::factory()->student()->create();
        [$test, $attempt] = $this->createCompletedAttempt($student, 'Reading Section Only');

        $attempt->update([
            'attempt_type' => 'section',
            'section_type' => 'reading_writing',
            'total_score' => null,
            'score_reading_writing' => null,
            'score_math' => null,
        ]);

        $response = $this->actingAs($student)->get(route('student.scores.show', $attempt));
        $response->assertOk();

        // Must display single-zone layout without section micro-cards
        $response->assertSee('sd-metrics-grid-single', false);
        $response->assertDontSee('sd-micro-card-rw');
        $response->assertDontSee('sd-micro-card-math');
    }
}

