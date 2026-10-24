<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\BlogPost;
use App\Models\Classroom;
use App\Models\ForumThread;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentHomeDiscoveryHubTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    private function createFullTest(string $title = 'Adaptive Full Practice Test 1'): Test
    {
        $test = Test::create([
            'title' => $title,
            'test_type' => 'full_length',
            'break_duration_minutes' => 10,
            'status' => 'active',
            'is_public' => true,
        ]);

        $section = Section::create([
            'test_id' => $test->id,
            'name' => 'Reading and Writing',
            'type' => 'reading_writing',
            'order' => 1,
        ]);

        Module::create([
            'section_id' => $section->id,
            'module_number' => 1,
            'difficulty_level' => 'standard',
            'duration_minutes' => 32,
            'total_questions' => 27,
            'order' => 1,
        ]);

        return $test;
    }

    public function test_fresh_student_sees_discovery_hub_with_test_preview_primary_emphasis(): void
    {
        $student = User::factory()->student()->create([
            'target_score' => null,
        ]);

        $response = $this->actingAs($student)->get('/home');

        $response->assertOk();

        // 1. Compact greeting & distinct join actions
        $response->assertSee('Join an exam')
            ->assertSee(route('exam-join.entry'), false)
            ->assertSee('Join a class')
            ->assertSee(route('student.classes.index', ['all' => 1]), false)
            ->assertDontSee('Join with a code');

        // Primary emphasis on Test Preview when neither in-progress attempt nor assignments exist
        $response->assertSee('Get started with Test Preview')
            ->assertSee('Try Test Preview')
            ->assertSee(route('test.preview'), false);

        // 2. Explore your study tools - all 5 tools present with descriptive links
        $response->assertSee('Explore your study tools')
            ->assertSee('Test Preview')
            ->assertSee('Explore sample questions and testing tools without timing or a scored result.')
            ->assertSee('Practice')
            ->assertSee('Browse available tests and choose a suitable practice format.')
            ->assertSee(route('home.practice'), false)
            ->assertSee('Classes &amp; Assignments', false)
            ->assertSee('Join a teacher’s class, find assigned work, and access class materials.', false)
            ->assertSee('Scores &amp; Progress', false)
            ->assertSee('Review your answers and see how your practice adds up.', false)
            ->assertSee('Guides &amp; Community', false)
            ->assertSee('Read teacher articles or ask questions in the forum.', false);

        // 3. How your practice connects sequence
        $response->assertSee('Getting started')
            ->assertSee('Complete practice')
            ->assertSee('Review your answers')
            ->assertSee('Open your score report');

        // 4. Consolidated progress summary with empty state explanation & unset target
        $response->assertSee('Getting started')
            ->assertSee('Find your starting point')
            ->assertSee('Not set')
            ->assertDontSee('Recommended from your recent practice'); // Random featured test removed
    }

    public function test_target_score_displays_saved_target_or_not_set_never_hardcoded_1600(): void
    {
        $unsetStudent = User::factory()->student()->create(['target_score' => null]);
        $responseUnset = $this->actingAs($unsetStudent)->get('/home');
        $responseUnset->assertOk()
            ->assertSee('Not set')
            ->assertSee(route('student.progress'), false);

        $setStudent = User::factory()->student()->create(['target_score' => 1480]);
        $responseSet = $this->actingAs($setStudent)->get('/home');
        $responseSet->assertOk()
            ->assertSee('1480')
            ->assertSee('value="1480"', false);
    }

    public function test_in_progress_attempt_is_surfaced_first_with_resume_emphasis(): void
    {
        $student = User::factory()->student()->create();
        $test = $this->createFullTest();
        $module = $test->sections->first()->modules->first();

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
            'current_module_id' => $module->id,
        ]);

        $response = $this->actingAs($student)->get('/home');
        $response->assertOk();

        // Resumable attempt receives primary emphasis with modal trigger
        $response->assertSee('Resume practice')
            ->assertSee('open-ready-modal')
            ->assertSee($attempt->ulid);

        // Discovery tools are still present on the page below current work
        $response->assertSee('Explore your study tools')
            ->assertSee('Getting started');
    }

    public function test_assignments_plus_active_practice_both_surfaced_before_discovery(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        $classroom = Classroom::create([
            'owner_id' => $teacher->id,
            'name' => 'Cohort Alpha',
            'status' => 'active',
        ]);

        $test = $this->createFullTest();
        $module = $test->sections->first()->modules->first();

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
            'current_module_id' => $module->id,
        ]);

        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'test_id' => $test->id,
            'title' => 'Assigned Diagnostic 1',
            'attempt_limit' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);

        AssignmentRecipient::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($student)->get('/home');
        $response->assertOk();

        // Both in-progress attempt and assignments are visible in Current Work
        $response->assertSee('Resume practice')
            ->assertSee('From your teacher')
            ->assertSee('Assigned Diagnostic 1')
            ->assertSee(route('student.assignments.show', ['assignment' => $assignment->ulid]), false);

        // Discovery tools are present below
        $response->assertSee('Explore your study tools');
    }

    public function test_scored_full_length_attempt_displays_consolidated_scores_and_rw_math_split(): void
    {
        $student = User::factory()->student()->create(['target_score' => 1500]);
        $test = $this->createFullTest();

        UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'attempt_type' => 'full',
            'total_score' => 1420,
            'score_reading_writing' => 700,
            'score_math' => 720,
            'completed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($student)->get('/home');
        $response->assertOk();

        // Consolidated summary shows latest score, personal best, and subscore split
        $response->assertSee('1420')
            ->assertSee('RW:')
            ->assertSee('700')
            ->assertSee('Math:')
            ->assertSee('720')
            ->assertSee('1500')
            ->assertDontSee('data-home-score-history')
            ->assertSee('Review your answers')
            ->assertSee('All score reports');
    }

    public function test_scored_section_only_attempt_displays_score_out_of_800_with_section_badge(): void
    {
        $student = User::factory()->student()->create();
        $test = $this->createFullTest();

        UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'attempt_type' => 'section',
            'section_type' => 'reading_writing',
            'total_score' => null,
            'score_reading_writing' => 670,
            'score_math' => null,
            'completed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($student)->get('/home');
        $response->assertOk();

        // Section attempt scores out of 800 with section badge
        $response->assertSee('670')
            ->assertSee('/ 800')
            ->assertSee('Reading &amp; Writing', false);
    }

    public function test_empty_resource_feeds_retain_discovery_links_without_large_empty_panels(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get('/home');
        $response->assertOk();

        // Empty blog feed has concise text and discovery link
        $response->assertSee('Study tips written by teachers will show up here.')
            ->assertSee('Browse blog archive')
            ->assertSee(route('blog.index'), false);

        // Empty forum feed has concise text and discovery link
        $response->assertSee('Ask the first question and a teacher will answer it.')
            ->assertSee('Open forum')
            ->assertSee(route('forum.index'), false);
    }

    public function test_experienced_student_with_no_actionable_work_gets_a_library_next_step(): void
    {
        $student = User::factory()->student()->create();
        $test = $this->createFullTest();

        UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'attempt_type' => 'full',
            'total_score' => 1350,
            'score_reading_writing' => 670,
            'score_math' => 680,
            'completed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($student)->get('/home');
        $response->assertOk();

        $response->assertSeeInOrder(['Open your score report', 'Your starting score'])
            ->assertSee('1350')
            ->assertDontSee('Get started with Test Preview')
            ->assertDontSee('From your teacher')
            ->assertDontSee('Resume practice');
    }

    public function test_weekly_activity_counts_practice_completions_and_questions_answered(): void
    {
        $student = User::factory()->student()->create();
        $test = $this->createFullTest();
        $module = $test->sections->first()->modules->first();

        $attempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'attempt_type' => 'full',
            'total_score' => 1200,
            'score_reading_writing' => 600,
            'score_math' => 600,
            'completed_at' => now(),
        ]);

        $teacher = User::factory()->teacher()->create();
        $q1 = Question::create([
            'stem' => 'Q1',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'reading_writing',
            'skill_domain' => 'craft_and_structure',
            'is_complete' => true,
            'is_pretest' => false,
            'created_by' => $teacher->id,
        ]);
        $q2 = Question::create([
            'stem' => 'Q2',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'reading_writing',
            'skill_domain' => 'craft_and_structure',
            'is_complete' => true,
            'is_pretest' => false,
            'created_by' => $teacher->id,
        ]);

        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $q1->id,
            'selected_answer' => 'A',
        ]);

        UserTestAnswer::create([
            'user_test_id' => $attempt->id,
            'module_id' => $module->id,
            'question_id' => $q2->id,
            'selected_answer' => '0',
        ]);

        $response = $this->actingAs($student)->get('/home');
        $response->assertOk();

        $weeklyActivity = $response->viewData('weeklyActivity');
        $this->assertNotNull($weeklyActivity);
        $this->assertSame(1, $weeklyActivity['practiceCount']);
        $this->assertSame(2, $weeklyActivity['questionsCount']);

        $response->assertSee('This week')
            ->assertSee('test completed')
            ->assertSee('questions answered');
    }

    public function test_score_history_chart_and_recent_work_display_up_to_specified_limits(): void
    {
        $student = User::factory()->student()->create();
        $test = $this->createFullTest();

        // Create 6 completed full tests
        for ($i = 1; $i <= 6; $i++) {
            UserTest::create([
                'user_id' => $student->id,
                'test_id' => $test->id,
                'status' => 'completed',
                'attempt_type' => 'full',
                'total_score' => 1000 + ($i * 50),
                'score_reading_writing' => 500 + ($i * 25),
                'score_math' => 500 + ($i * 25),
                'completed_at' => now()->subDays(7 - $i),
            ]);
        }

        $response = $this->actingAs($student)->get('/home');
        $response->assertOk();

        // Recent work should have max 3 attempts
        $recentWork = $response->viewData('recentWorkAttempts');
        $this->assertCount(3, $recentWork);

        // Chart trend should have last 5 attempts
        $homeScoreTrend = $response->viewData('homeScoreTrend');
        $this->assertCount(5, $homeScoreTrend['attempts']);

        // Assert recent work and score history sections are rendered
        $response->assertSee('Your scores over time')
            ->assertSee('Recent work')
            ->assertSee('Review')
            ->assertSee('All score reports');
    }

    public function test_student_navigation_has_labeled_groups_and_student_rail(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get('/home');
        $response->assertOk();

        $response->assertSee('icon-rail--student')
            ->assertSee('Study')
            ->assertSee('Progress')
            ->assertSee('Community')
            ->assertSee('Test Library')
            ->assertSee('Classes');
    }

    public function test_weekly_activity_boundary_handling_and_more_than_ten_completions(): void
    {
        $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');
        // Freeze time to Wednesday Oct 21, 2026 12:00:00 Asia/Ho_Chi_Minh
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::create(2026, 10, 21, 12, 0, 0, $tz));

        $student = User::factory()->student()->create();
        $test = $this->createFullTest();
        $module = $test->sections->first()->modules->first();

        $startOfWeek = now($tz)->startOfWeek(\Carbon\Carbon::MONDAY); // 2026-10-19 00:00:00
        $endOfWeek = (clone $startOfWeek)->addWeek(); // 2026-10-26 00:00:00

        // 1. Exactly at previous Sunday 23:59:59 (should be EXCLUDED)
        UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'attempt_type' => 'full',
            'total_score' => 1100,
            'score_reading_writing' => 550,
            'score_math' => 550,
            'completed_at' => (clone $startOfWeek)->subSecond(),
        ]);

        // 2. Exactly at Monday 00:00:00 (should be INCLUDED)
        $mondayAttempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'attempt_type' => 'full',
            'total_score' => 1150,
            'score_reading_writing' => 575,
            'score_math' => 575,
            'completed_at' => (clone $startOfWeek),
        ]);

        // 3. Exactly at next Monday 00:00:00 (should be EXCLUDED)
        UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'attempt_type' => 'full',
            'total_score' => 1200,
            'score_reading_writing' => 600,
            'score_math' => 600,
            'completed_at' => (clone $endOfWeek),
        ]);

        // 4. Create 11 more completed tests inside the current week (total 12 completions in week)
        $teacher = User::factory()->teacher()->create();
        $q1 = Question::create([
            'stem' => 'Test Q1',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'reading_writing',
            'skill_domain' => 'craft_and_structure',
            'is_complete' => true,
            'created_by' => $teacher->id,
        ]);
        $q2 = Question::create([
            'stem' => 'Test Q2',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'reading_writing',
            'skill_domain' => 'craft_and_structure',
            'is_complete' => true,
            'created_by' => $teacher->id,
        ]);
        $q3 = Question::create([
            'stem' => 'Test Q3',
            'question_type' => 'multiple_choice',
            'difficulty' => 'easy',
            'section_type' => 'reading_writing',
            'skill_domain' => 'craft_and_structure',
            'is_complete' => true,
            'created_by' => $teacher->id,
        ]);

        for ($i = 1; $i <= 11; $i++) {
            $att = UserTest::create([
                'user_id' => $student->id,
                'test_id' => $test->id,
                'status' => 'completed',
                'attempt_type' => 'full',
                'total_score' => 1000 + $i,
                'score_reading_writing' => 500,
                'score_math' => 500,
                'completed_at' => (clone $startOfWeek)->addHours($i * 4),
            ]);

            // Add answers: one with "0", one with "A", one empty "" (omitted)
            UserTestAnswer::create([
                'user_test_id' => $att->id,
                'module_id' => $module->id,
                'question_id' => $q1->id,
                'selected_answer' => '0',
            ]);
            UserTestAnswer::create([
                'user_test_id' => $att->id,
                'module_id' => $module->id,
                'question_id' => $q2->id,
                'selected_answer' => 'A',
            ]);
            UserTestAnswer::create([
                'user_test_id' => $att->id,
                'module_id' => $module->id,
                'question_id' => $q3->id,
                'selected_answer' => '',
            ]);
        }

        // 5. Absorbed section attempt inside the week for the same test (should be EXCLUDED)
        $absorbedAttempt = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'attempt_type' => 'section',
            'section_type' => 'reading_writing',
            'total_score' => null,
            'score_reading_writing' => 500,
            'score_math' => null,
            'completed_at' => (clone $startOfWeek)->addHours(2),
        ]);
        UserTestAnswer::create([
            'user_test_id' => $absorbedAttempt->id,
            'module_id' => $module->id,
            'question_id' => $q1->id,
            'selected_answer' => 'A',
        ]);

        $response = $this->actingAs($student)->get('/home');
        $response->assertOk();

        $weekly = $response->viewData('weeklyActivity');
        // 1 (Monday 00:00) + 11 (within week) = 12 total
        $this->assertSame(12, $weekly['practiceCount']);
        // 11 tests * 2 non-empty answers ("0" and "A", skipping "") = 22 questions
        $this->assertSame(22, $weekly['questionsCount']);

        \Carbon\Carbon::setTestNow();
    }

    public function test_assignment_hero_replaces_one_preview_row_without_pulling_in_a_fourth_assignment(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $classroom = Classroom::create(['owner_id' => $teacher->id, 'name' => 'Guidance cohort', 'status' => 'active']);
        $test = $this->createFullTest();
        for ($index = 1; $index <= 5; $index++) {
            $assignment = Assignment::create([
                'classroom_id' => $classroom->id, 'teacher_id' => $teacher->id, 'test_id' => $test->id,
                'title' => "Guidance work {$index}", 'status' => 'published', 'attempt_limit' => 1,
                'published_at' => now()->subDay(), 'due_at' => now()->addDays($index),
            ]);
            AssignmentRecipient::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'active', 'assigned_at' => now()]);
        }
        $response = $this->actingAs($student)->get('/home')->assertOk();
        $response->assertSeeInOrder(['Guidance work 1', 'From your teacher', 'Guidance work 2', 'Guidance work 3', 'Explore your study tools'])
            ->assertDontSee('Guidance work 4')->assertDontSee('Guidance work 5');
        $this->assertSame(1, substr_count($response->getContent(), 'Guidance work 1'));
        $this->assertSame(5, $response->viewData('assignmentFocus')['open']);
    }

    public function test_chart_uses_elapsed_time_and_preserves_complete_accessible_values(): void
    {
        $student = User::factory()->student()->create(['target_score' => 1500]);
        $test = $this->createFullTest();
        foreach (['2026-10-01 12:00:00', '2026-10-02 12:00:00', '2026-10-11 12:00:00'] as $index => $date) {
            UserTest::create(['user_id' => $student->id, 'test_id' => $test->id, 'status' => 'completed', 'attempt_type' => 'full',
                'total_score' => 1300 + $index * 10, 'score_reading_writing' => 650, 'score_math' => 650 + $index * 10, 'completed_at' => $date]);
        }
        $response = $this->actingAs($student)->get('/home')->assertOk();
        $response->assertSee('Full tests spaced by completion date.')
            ->assertSee('inset-inline-start: 14%', false)->assertSee('View score data')
            ->assertSee('Oct 2, 2026')->assertSee('1310 / 1600');
        $this->assertSame(86400, $response->viewData('homeScoreTrend')['attempts'][1]['timestamp'] - $response->viewData('homeScoreTrend')['attempts'][0]['timestamp']);
    }

    public function test_same_time_points_are_centered_and_missing_dates_fall_back_to_attempt_order(): void
    {
        $student = User::factory()->student()->create();
        $test = $this->createFullTest();
        $attempts = [];
        foreach ([1300, 1320] as $score) {
            $attempts[] = UserTest::create(['user_id' => $student->id, 'test_id' => $test->id, 'status' => 'completed', 'attempt_type' => 'full',
                'total_score' => $score, 'score_reading_writing' => 650, 'score_math' => $score - 650, 'completed_at' => '2026-10-01 12:00:00']);
        }
        $response = $this->actingAs($student)->get('/home')->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'inset-inline-start: 50%'));
        $response->assertSee('1300 / 1600')->assertSee('1320 / 1600');
        $attempts[0]->update(['completed_at' => null]);
        $this->actingAs($student)->get('/home')->assertOk()->assertSee('Recent full tests in attempt order.')
            ->assertSee('Undated')->assertDontSee('Full tests spaced by completion date.');
    }

    public function test_limited_recommendation_evidence_is_explained_without_promising_a_drill(): void
    {
        $student = User::factory()->student()->create();
        $this->mock(\App\Services\ProgressDossierService::class, function ($mock) {
            $mock->shouldReceive('studySuggestion')->once()->andReturn(['skill' => 'Inferences', 'domain' => 'Information and Ideas', 'correct' => 0, 'total' => 2]);
        });
        $this->actingAs($student)->get('/home')->assertOk()->assertSee('Suggested focus')->assertSee('Correct on 0 of 2 recent questions.')
            ->assertSee('This is limited evidence.')->assertSee('Browse practice tests')->assertDontSee('Practice 10 questions');
    }

    public function test_more_actionable_assignments_than_limit_maintains_accurate_headline_and_deduplicates(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        $classroom = Classroom::create([
            'owner_id' => $teacher->id,
            'name' => 'Cohort Beta',
            'status' => 'active',
        ]);

        $test = $this->createFullTest();
        $module = $test->sections->first()->modules->first();

        // Create 5 published assignments
        $assignments = [];
        for ($i = 1; $i <= 5; $i++) {
            $a = Assignment::create([
                'classroom_id' => $classroom->id,
                'teacher_id' => $teacher->id,
                'test_id' => $test->id,
                'title' => "Assignment {$i}",
                'attempt_limit' => 1,
                'status' => 'published',
                'published_at' => now()->subHours(6 - $i),
                'due_at' => now()->addDays($i),
            ]);
            AssignmentRecipient::create([
                'assignment_id' => $a->id,
                'student_id' => $student->id,
                'status' => 'active',
                'assigned_at' => now(),
            ]);
            $assignments[] = $a;
        }

        // Student has an in-progress attempt for Assignment 1
        UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'assignment_id' => $assignments[0]->id,
            'status' => 'in_progress',
            'current_module_id' => $module->id,
        ]);

        $response = $this->actingAs($student)->get('/home');
        $response->assertOk();

        $displayedAssignments = $response->viewData('assignments');
        // Max 3 displayed
        $this->assertCount(3, $displayedAssignments);

        // Assignment 1 was deduplicated because it is already surfaced as $inProgressAttempt in Section A
        $this->assertFalse($displayedAssignments->contains('id', $assignments[0]->id));

        // Headline open count remains accurate (5 total open assignments)
        $focus = $response->viewData('assignmentFocus');
        $this->assertSame(5, $focus['open']);
    }
}
