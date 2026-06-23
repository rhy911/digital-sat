<?php

namespace Tests\Feature;

use App\Livewire\Teacher\Workspace;
use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\Classroom;
use App\Models\ClassroomMembership;
use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use App\Models\UserTestAnswer;
use App\Notifications\AssignmentPublishedNotification;
use App\Notifications\MembershipDecisionNotification;
use App\Notifications\TeacherApprovalDecisionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TeacherClassManagementTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): User { return User::factory()->teacher()->create(); }
    private function student(): User { return User::factory()->student()->create(); }
    private function classroom(User $teacher): Classroom { return Classroom::create(['owner_id' => $teacher->id, 'name' => 'SAT Cohort']); }
    private function testFor(User $teacher): Test
    {
        return Test::create(['title' => 'Assigned SAT', 'test_type' => 'custom_test', 'status' => 'active', 'created_by' => $teacher->id, 'is_public' => false]);
    }
    private function assignment(Classroom $classroom, Test $test, array $extra = []): Assignment
    {
        return Assignment::create(array_merge(['classroom_id' => $classroom->id, 'teacher_id' => $classroom->owner_id, 'test_id' => $test->id, 'title' => 'Weekly SAT', 'attempt_limit' => 2], $extra));
    }
    private function complete(Test $test): void
    {
        $section = Section::create(['test_id' => $test->id, 'name' => 'Math', 'type' => 'math', 'order' => 1, 'created_by' => $test->created_by]);
        $module = Module::create(['section_id' => $section->id, 'module_number' => 1, 'difficulty_level' => 'standard', 'duration_minutes' => 35, 'total_questions' => 1, 'order' => 1, 'created_by' => $test->created_by]);
        $question = Question::create(['stem' => 'What is 1 + 1?', 'question_type' => 'student_produced_response', 'difficulty' => 'easy', 'section_type' => 'math', 'skill_domain' => 'algebra', 'calculator_allowed' => true, 'is_complete' => true, 'created_by' => $test->created_by]);
        $module->questions()->attach($question->id, ['position' => 1]);
    }

    public function test_pending_teacher_cannot_open_workspace_until_admin_approves(): void
    {
        Notification::fake();
        $teacher = User::factory()->teacher(false)->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($teacher)->get(route('teacher.workspace'))->assertRedirect(route('teacher.application.status'));
        $this->actingAs($admin)->post(route('admin.teacher-applications.decide', $teacher), ['decision' => 'approved'])->assertRedirect();

        $this->assertSame('approved', $teacher->fresh()->teacher_approval_status);
        Notification::assertSentTo($teacher, TeacherApprovalDecisionNotification::class);
        $this->actingAs($teacher->fresh())->get(route('teacher.workspace'))->assertRedirect(route('home'));
    }

    public function test_teacher_home_exposes_workspace_tabs_and_separated_test_builder(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($teacher)
            ->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder(['Progress', 'Classes', 'Reports', 'Practice', 'Test Builder'], false)
            ->assertSee('ds-nav-destination', false)
            ->assertSee('href="'.route('home-dashboard.index').'"', false)
            ->assertSee("Livewire.dispatch('teacher-workspace-section'", false);
    }

    public function test_admin_management_pages_share_workspace_navigation(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.teacher-applications.index'))
            ->assertOk()
            ->assertSee('Teacher applications')
            ->assertSee('Applications')
            ->assertSee('Test Builder');
    }

    public function test_teacher_workspace_switches_sections_and_class_status_without_route_navigation(): void
    {
        $teacher = $this->teacher();
        $activeClass = $this->classroom($teacher);
        $archivedClass = Classroom::create(['owner_id' => $teacher->id, 'name' => 'Archived Cohort', 'status' => 'archived']);
        $assignment = $this->assignment($activeClass, $this->testFor($teacher), ['title' => 'Workspace Assignment']);

        Livewire::actingAs($teacher)
            ->test(Workspace::class)
            ->assertSet('section', 'classes')
            ->assertSet('classStatus', 'active')
            ->assertSee($activeClass->name)
            ->assertDontSee($archivedClass->name)
            ->call('showClassStatus', 'archived')
            ->assertSet('classStatus', 'archived')
            ->assertSee($archivedClass->name)
            ->assertDontSee($activeClass->name)
            ->call('showSection', 'assignments')
            ->assertSet('section', 'assignments')
            ->assertSee($assignment->title);

        $this->assertSame('assignments', session('teacher_workspace.section'));
        $this->assertSame('archived', session('teacher_workspace.class_status'));

        Livewire::actingAs($teacher)->test(Workspace::class)->call('showProgress');
        $this->assertSame('progress', session('teacher_home.tab'));

        $this->actingAs($teacher)
            ->get(route('teacher.assignments.index'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('teacher_workspace.section', 'assignments')
            ->assertSessionHas('teacher_home.tab', 'reports');

        $this->actingAs($teacher)
            ->get(route('home'))
            ->assertOk()
            ->assertSee($assignment->title)
            ->assertSee("Livewire.dispatch('teacher-workspace-section'", false)
            ->assertDontSee('?section=', false)
            ->assertDontSee('?status=', false);

        $this->actingAs($teacher)
            ->get(route('teacher.classes.index'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('teacher_workspace.section', 'classes')
            ->assertSessionHas('teacher_home.tab', 'classes');
    }

    public function test_teacher_workspace_restores_session_state_and_keeps_pagination_out_of_url(): void
    {
        $teacher = $this->teacher();
        foreach (range(1, 13) as $index) {
            Classroom::create(['owner_id' => $teacher->id, 'name' => "Archived Cohort {$index}", 'status' => 'archived']);
        }

        session([
            'teacher_workspace.section' => 'classes',
            'teacher_workspace.class_status' => 'archived',
        ]);

        Livewire::actingAs($teacher)
            ->test(Workspace::class)
            ->assertSet('section', 'classes')
            ->assertSet('classStatus', 'archived')
            ->assertSee('Archived Cohort')
            ->assertSee('wire:click="gotoPage', false)
            ->assertDontSee('classesPage=', false);
    }

    public function test_workspace_scopes_teacher_data_and_allows_admin_oversight(): void
    {
        $teacher = $this->teacher();
        $other = $this->teacher();
        $admin = User::factory()->admin()->create();
        $owned = $this->classroom($teacher);
        $otherClass = Classroom::create(['owner_id' => $other->id, 'name' => 'Other Teacher Cohort']);

        Livewire::actingAs($teacher)
            ->test(Workspace::class)
            ->assertSee($owned->name)
            ->assertDontSee($otherClass->name);

        Livewire::actingAs($admin)
            ->test(Workspace::class)
            ->assertSee($owned->name)
            ->assertSee($otherClass->name);
    }

    public function test_assignment_report_back_link_returns_to_its_origin(): void
    {
        $teacher = $this->teacher();
        $classroom = $this->classroom($teacher);
        $assignment = $this->assignment($classroom, $this->testFor($teacher));

        $this->actingAs($teacher)
            ->get(route('teacher.assignments.show', ['assignment' => $assignment, 'from' => 'workspace']))
            ->assertOk()
            ->assertSee('Back to assignments &amp; reports', false)
            ->assertSee('href="'.route('teacher.assignments.index').'"', false);

        $this->actingAs($teacher)
            ->get(route('teacher.assignments.show', ['assignment' => $assignment, 'from' => 'invalid']))
            ->assertOk()
            ->assertSee('Back to '.$classroom->name)
            ->assertSee('href="'.route('teacher.classes.show', $classroom).'#assignments"', false);
    }

    public function test_class_detail_uses_home_shell_and_marks_classes_current(): void
    {
        $teacher = $this->teacher();
        $classroom = $this->classroom($teacher);

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', $classroom))
            ->assertOk()
            ->assertSee('class="ds-topbar"', false)
            ->assertSee('class="ds-teacher-workspace teacher-detail"', false)
            ->assertSee('href="'.route('teacher.classes.index').'"', false)
            ->assertDontSee('class="teacher-nav"', false);
    }

    public function test_join_request_requires_owner_approval_and_sends_decision(): void
    {
        Notification::fake();
        $teacher = $this->teacher(); $student = $this->student(); $classroom = $this->classroom($teacher);

        $this->actingAs($student)->post(route('student.classes.join'), ['join_code' => $classroom->join_code])->assertRedirect();
        $membership = ClassroomMembership::firstOrFail();
        $this->assertSame('pending', $membership->status);

        $this->actingAs($teacher)->post(route('teacher.memberships.approve', $membership))->assertRedirect();
        $this->assertSame('active', $membership->fresh()->status);
        Notification::assertSentTo($student, MembershipDecisionNotification::class);
    }

    public function test_publish_assigns_active_roster_and_late_joiner_receives_open_work(): void
    {
        Notification::fake();
        $teacher = $this->teacher(); $first = $this->student(); $late = $this->student();
        $classroom = $this->classroom($teacher); $test = $this->testFor($teacher);
        $this->complete($test);
        ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $first->id, 'status' => 'active', 'requested_at' => now()]);
        $assignment = $this->assignment($classroom, $test);

        $this->actingAs($teacher)->post(route('teacher.assignments.publish', $assignment))->assertRedirect();
        $this->assertDatabaseHas('assignment_recipients', ['assignment_id' => $assignment->id, 'student_id' => $first->id, 'status' => 'active']);
        Notification::assertSentTo($first, AssignmentPublishedNotification::class);

        $membership = ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $late->id, 'status' => 'pending', 'requested_at' => now()]);
        $this->actingAs($teacher)->post(route('teacher.memberships.approve', $membership));
        $this->assertDatabaseHas('assignment_recipients', ['assignment_id' => $assignment->id, 'student_id' => $late->id, 'status' => 'active']);
    }

    public function test_publishing_assignment_locks_private_test_and_attempt_limit_is_enforced(): void
    {
        $teacher = $this->teacher(); $student = $this->student(); $classroom = $this->classroom($teacher); $test = $this->testFor($teacher);
        $this->complete($test);
        ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'active']);
        $assignment = $this->assignment($classroom, $test, ['attempt_limit' => 1]);

        $assignment = app(\App\Services\AssignmentService::class)->publish($assignment);
        $this->assertNotNull($test->fresh()->content_locked_at);

        $response = $this->actingAs($student)->post(route('student.assignments.start', $assignment))->assertRedirect();
        $this->actingAs($student)->get($response->headers->get('Location'))->assertOk();
        $attempt = UserTest::firstOrFail();
        $this->assertSame(1, $attempt->attempt_number);
        $attempt->update(['status' => 'completed', 'completed_at' => now(), 'total_score' => 1200]);
        $this->actingAs($student)->from(route('student.assignments.show', $assignment))->post(route('student.assignments.start', $assignment))->assertSessionHasErrors('assignment');
    }

    public function test_due_time_blocks_new_attempt_but_existing_attempt_can_resume(): void
    {
        $teacher = $this->teacher(); $student = $this->student(); $classroom = $this->classroom($teacher); $test = $this->testFor($teacher);
        $assignment = $this->assignment($classroom, $test, ['status' => 'published', 'published_at' => now(), 'due_at' => now()->subMinute()]);
        AssignmentRecipient::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'active', 'assigned_at' => now()]);

        $this->actingAs($student)->post(route('student.assignments.start', $assignment))->assertSessionHasErrors('assignment');
        $attempt = UserTest::create(['user_id' => $student->id, 'test_id' => $test->id, 'assignment_id' => $assignment->id, 'attempt_number' => 1, 'status' => 'in_progress']);
        $resolved = app(\App\Services\AssignmentAttemptService::class)->startOrResume($assignment, $student);
        $this->assertTrue($attempt->is($resolved));
    }

    public function test_leaving_withdraws_recipient_without_deleting_history(): void
    {
        $teacher = $this->teacher(); $student = $this->student(); $classroom = $this->classroom($teacher); $test = $this->testFor($teacher);
        $membership = ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'active']);
        $assignment = $this->assignment($classroom, $test, ['status' => 'published']);
        $recipient = AssignmentRecipient::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'active', 'assigned_at' => now()]);
        $attempt = UserTest::create(['user_id' => $student->id, 'test_id' => $test->id, 'assignment_id' => $assignment->id, 'attempt_number' => 1, 'status' => 'completed']);

        $this->actingAs($student)->post(route('student.classes.leave', $membership))->assertRedirect();
        $this->assertSame('left', $membership->fresh()->status);
        $this->assertSame('withdrawn', $recipient->fresh()->status);
        $this->assertDatabaseHas('user_tests', ['id' => $attempt->id]);
    }

    public function test_teacher_cannot_manage_another_teachers_class_or_report(): void
    {
        $owner = $this->teacher(); $other = $this->teacher(); $classroom = $this->classroom($owner); $assignment = $this->assignment($classroom, $this->testFor($owner));
        $this->actingAs($other)->get(route('teacher.classes.show', $classroom))->assertForbidden();
        $this->actingAs($other)->get(route('teacher.assignments.show', $assignment))->assertForbidden();
    }

    public function test_locked_test_rejects_structural_edits(): void
    {
        $teacher = $this->teacher(); $test = $this->testFor($teacher); $classroom = $this->classroom($teacher);
        $this->assignment($classroom, $test, ['status' => 'published', 'published_at' => now()]);
        $this->actingAs($teacher)->put(route('home-dashboard.tests.update', $test), ['title' => $test->title, 'test_type' => 'short_test', 'status' => 'active'])->assertSessionHasErrors('test');
    }

    public function test_incomplete_test_cannot_be_published(): void
    {
        $teacher = $this->teacher(); $classroom = $this->classroom($teacher); $assignment = $this->assignment($classroom, $this->testFor($teacher));
        $this->actingAs($teacher)->post(route('teacher.assignments.publish', $assignment))->assertSessionHasErrors('assignment');
        $this->assertSame('draft', $assignment->fresh()->status);
    }

    public function test_report_uses_highest_score_and_excludes_withdrawn_recipient(): void
    {
        $teacher = $this->teacher(); $active = $this->student(); $withdrawn = $this->student(); $classroom = $this->classroom($teacher); $test = $this->testFor($teacher);
        $this->complete($test);
        $module = $test->sections()->firstOrFail()->modules()->firstOrFail();
        $assignment = $this->assignment($classroom, $test, ['status' => 'published', 'attempt_limit' => 3]);
        AssignmentRecipient::create(['assignment_id' => $assignment->id, 'student_id' => $active->id, 'status' => 'active', 'assigned_at' => now()]);
        AssignmentRecipient::create(['assignment_id' => $assignment->id, 'student_id' => $withdrawn->id, 'status' => 'withdrawn', 'assigned_at' => now(), 'withdrawn_at' => now()]);
        UserTest::create(['user_id' => $active->id, 'test_id' => $test->id, 'assignment_id' => $assignment->id, 'attempt_number' => 1, 'status' => 'completed', 'total_score' => 1000, 'completed_at' => now()]);
        UserTest::create(['user_id' => $active->id, 'test_id' => $test->id, 'assignment_id' => $assignment->id, 'attempt_number' => 2, 'status' => 'completed', 'total_score' => 1300, 'completed_at' => now()]);
        UserTest::create(['user_id' => $active->id, 'test_id' => $test->id, 'assignment_id' => $assignment->id, 'attempt_number' => 3, 'status' => 'in_progress', 'current_module_id' => $module->id, 'current_module_elapsed_seconds' => 754]);
        UserTest::create(['user_id' => $withdrawn->id, 'test_id' => $test->id, 'assignment_id' => $assignment->id, 'attempt_number' => 1, 'status' => 'completed', 'total_score' => 1500, 'completed_at' => now()]);

        $this->actingAs($teacher)
            ->get(route('teacher.assignments.show', $assignment))
            ->assertOk()
            ->assertSee('View attempts')
            ->assertSee('class="attempt-detail-trigger"', false)
            ->assertSee('x-data', false)
            ->assertSee("x-on:click.prevent=\"\$dispatch('open-modal'", false)
            ->assertSee('role="dialog"', false)
            ->assertSee('Attempts for '.$active->name);

        $this->actingAs($teacher)
            ->getJson(route('teacher.assignments.attempt-monitor', [$assignment, $active]))
            ->assertOk()
            ->assertJsonPath('html', fn($html) => str_contains($html, 'Active now'))
            ->assertJsonPath('html', fn($html) => str_contains($html, 'Math'))
            ->assertJsonPath('html', fn($html) => str_contains($html, 'Module'))
            ->assertJsonPath('html', fn($html) => str_contains($html, '0 / 1'))
            ->assertJsonPath('html', fn($html) => str_contains($html, '12:34'))
            ->assertJsonPath('html', fn($html) => str_contains($html, 'Attempt 2'))
            ->assertJsonPath('html', fn($html) => str_contains($html, 'Estimated 1300'))
            ->assertJsonPath('html', fn($html) => str_contains($html, 'No responses saved yet'));

        $report = app(\App\Services\AssignmentReportService::class)->build($assignment);
        $this->assertSame(1, $report['metrics']['assigned']);
        $this->assertSame(1300, $report['metrics']['average_score']);
        $this->assertSame(1300, $report['rows']->first(fn ($row) => $row['recipient']->student_id === $active->id)['best']->total_score);
    }

    public function test_teacher_attempt_monitor_returns_live_recipient_snapshot(): void
    {
        $teacher = $this->teacher();
        $otherTeacher = $this->teacher();
        $student = $this->student();
        $outsider = $this->student();
        $classroom = $this->classroom($teacher);
        $test = $this->testFor($teacher);
        $this->complete($test);
        $module = $test->sections()->firstOrFail()->modules()->firstOrFail();
        $question = $module->questions()->firstOrFail();
        $assignment = $this->assignment($classroom, $test, ['status' => 'published', 'attempt_limit' => 2]);
        AssignmentRecipient::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'active', 'assigned_at' => now()]);
        $completed = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'assignment_id' => $assignment->id,
            'attempt_number' => 1,
            'status' => 'completed',
            'total_score' => 1200,
            'completed_at' => now(),
        ]);
        $active = UserTest::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'assignment_id' => $assignment->id,
            'attempt_number' => 2,
            'status' => 'in_progress',
            'current_module_id' => $module->id,
            'current_module_elapsed_seconds' => 75,
        ]);
        UserTestAnswer::create([
            'user_test_id' => $active->id,
            'module_id' => $module->id,
            'question_id' => $question->id,
            'selected_answer' => '2',
            'is_correct' => true,
        ]);

        $url = route('teacher.assignments.attempt-monitor', [$assignment, $student]);
        $response = $this->actingAs($teacher)->getJson($url.'?active_attempt='.$completed->id);

        $response->assertOk()
            ->assertJsonStructure(['html', 'updated_at'])
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'data-attempt-monitor')
                && str_contains($html, 'data-active-attempt="'.$completed->id.'"')
                && str_contains($html, 'Answered: 2')
                && str_contains($html, '1 / 1')
                && str_contains($html, '1:15')
                && str_contains($html, 'Live updates'));

        $this->actingAs($otherTeacher)->getJson($url)->assertForbidden();
        $this->actingAs($teacher)
            ->getJson(route('teacher.assignments.attempt-monitor', [$assignment, $outsider]))
            ->assertNotFound();
    }

    public function test_ajax_teacher_login_returns_teacher_workspace_redirect(): void
    {
        $teacher = $this->teacher();
        $response = $this->postJson(route('signin'), ['email' => $teacher->email, 'password' => 'password', 'role' => 'teacher']);
        $response->assertOk()->assertJsonPath('redirect', route('home'));
    }

    public function test_join_link_survives_ajax_login_and_prefills_code(): void
    {
        $student = $this->student(); $classroom = $this->classroom($this->teacher());
        $this->get(route('student.classes.join-link', $classroom->join_code))->assertRedirect('/signin');
        $login = $this->postJson(route('signin'), ['email' => $student->email, 'password' => 'password', 'role' => 'student']);
        $login->assertOk()->assertJsonPath('redirect', route('student.classes.join-link', $classroom->join_code));
        $this->actingAs($student)->get(route('student.classes.join-link', $classroom->join_code))->assertRedirect(route('student.classes.index', ['code' => $classroom->join_code]));
    }

    public function test_rotated_join_code_invalidates_old_code(): void
    {
        $teacher = $this->teacher(); $student = $this->student(); $classroom = $this->classroom($teacher); $oldCode = $classroom->join_code;
        $this->actingAs($teacher)->post(route('teacher.classes.rotate-code', $classroom))->assertRedirect();
        $newCode = $classroom->fresh()->join_code;
        $this->assertNotSame($oldCode, $newCode);
        $this->actingAs($student)->post(route('student.classes.join'), ['join_code' => $oldCode])->assertSessionHasErrors('join_code');
        $this->actingAs($student)->post(route('student.classes.join'), ['join_code' => $newCode])->assertRedirect();
        $this->assertDatabaseHas('classroom_memberships', ['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'pending']);
    }

    public function test_archiving_closes_work_blocks_new_starts_and_preserves_resume(): void
    {
        $teacher = $this->teacher(); $started = $this->student(); $newStudent = $this->student(); $classroom = $this->classroom($teacher); $test = $this->testFor($teacher);
        $this->complete($test);
        $assignment = app(\App\Services\AssignmentService::class)->publish($this->assignment($classroom, $test));
        $this->assertNotNull($test->fresh()->content_locked_at);
        foreach ([$started, $newStudent] as $student) AssignmentRecipient::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'active', 'assigned_at' => now()]);
        $attempt = UserTest::create(['user_id' => $started->id, 'test_id' => $test->id, 'assignment_id' => $assignment->id, 'attempt_number' => 1, 'status' => 'in_progress']);

        $this->actingAs($teacher)->post(route('teacher.classes.archive', $classroom))->assertRedirect(route('teacher.classes.index'));
        $this->assertSame('archived', $classroom->fresh()->status);
        $this->assertSame('closed', $assignment->fresh()->status);
        $this->assertNull($test->fresh()->content_locked_at);
        $this->assertTrue($attempt->is(app(\App\Services\AssignmentAttemptService::class)->startOrResume($assignment->fresh(), $started)));
        try {
            app(\App\Services\AssignmentAttemptService::class)->startOrResume($assignment->fresh(), $newStudent);
            $this->fail('Archived class allowed a new attempt.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('assignment', $e->errors());
        }
        $this->actingAs($teacher)->get(route('home'))->assertDontSee($classroom->name);
        session(['teacher_workspace.class_status' => 'archived']);
        $this->actingAs($teacher)->get(route('home'))->assertSee($classroom->name);
    }

    public function test_future_assignment_blocks_start_until_available(): void
    {
        $teacher = $this->teacher(); $student = $this->student(); $classroom = $this->classroom($teacher); $test = $this->testFor($teacher);
        $assignment = $this->assignment($classroom, $test, ['status' => 'published', 'available_at' => now()->addHour(), 'due_at' => now()->addHours(2)]);
        AssignmentRecipient::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'active', 'assigned_at' => now()]);
        $this->actingAs($student)->post(route('student.assignments.start', $assignment))->assertSessionHasErrors('assignment');
        $this->assertDatabaseMissing('user_tests', ['assignment_id' => $assignment->id, 'user_id' => $student->id]);
    }

    public function test_student_can_open_assignments_scoped_to_one_class(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $firstClass = $this->classroom($teacher);
        $secondClass = Classroom::create(['owner_id' => $teacher->id, 'name' => 'Second Cohort']);
        $test = $this->testFor($teacher);
        $firstAssignment = $this->assignment($firstClass, $test, ['title' => 'First Class Work', 'status' => 'published']);
        $secondAssignment = $this->assignment($secondClass, $test, ['title' => 'Second Class Work', 'status' => 'published']);

        ClassroomMembership::create(['classroom_id' => $firstClass->id, 'student_id' => $student->id, 'status' => 'active']);
        ClassroomMembership::create(['classroom_id' => $secondClass->id, 'student_id' => $student->id, 'status' => 'active']);
        AssignmentRecipient::create(['assignment_id' => $firstAssignment->id, 'student_id' => $student->id, 'status' => 'active', 'assigned_at' => now()]);
        AssignmentRecipient::create(['assignment_id' => $secondAssignment->id, 'student_id' => $student->id, 'status' => 'active', 'assigned_at' => now()]);

        $this->actingAs($student)
            ->get(route('student.assignments.index', ['classroom' => $firstClass->id]))
            ->assertOk()
            ->assertSee('SAT Cohort assignments')
            ->assertSee('First Class Work')
            ->assertDontSee('Second Class Work')
            ->assertSee('wire:navigate', false);
    }

    public function test_question_content_is_immutable_after_test_lock(): void
    {
        $teacher = $this->teacher(); $test = $this->testFor($teacher); $this->complete($test);
        $this->assignment($this->classroom($teacher), $test, ['status' => 'published', 'published_at' => now()]);
        $question = $test->sections->first()->modules->first()->questions->first();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $question->update(['stem' => 'Changed after assignment started']);
    }

    public function test_new_student_gets_assigned_to_both_published_and_closed_assignments(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $classroom = $this->classroom($teacher);
        $test = $this->testFor($teacher);

        $publishedAssignment = $this->assignment($classroom, $test, ['status' => 'published', 'published_at' => now(), 'due_at' => null]);
        $closedAssignment = $this->assignment($classroom, $test, ['status' => 'closed', 'published_at' => now()->subDay(), 'closed_at' => now(), 'due_at' => now()->subHours(12)]);

        $membership = ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'pending', 'requested_at' => now()]);
        $this->actingAs($teacher)->post(route('teacher.memberships.approve', $membership));

        $this->assertDatabaseHas('assignment_recipients', ['assignment_id' => $publishedAssignment->id, 'student_id' => $student->id, 'status' => 'active']);
        $this->assertDatabaseHas('assignment_recipients', ['assignment_id' => $closedAssignment->id, 'student_id' => $student->id, 'status' => 'active']);
    }

    public function test_reopening_assignment_assigns_all_active_members(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $classroom = $this->classroom($teacher);
        $test = $this->testFor($teacher);
        $this->complete($test);

        $assignment = $this->assignment($classroom, $test, ['status' => 'closed', 'published_at' => now()->subDay(), 'closed_at' => now(), 'due_at' => null]);
        $membership = ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'active', 'requested_at' => now()]);

        AssignmentRecipient::where('assignment_id', $assignment->id)->where('student_id', $student->id)->delete();

        $this->actingAs($teacher)->post(route('teacher.assignments.reopen', $assignment))->assertRedirect();

        $this->assertSame('published', $assignment->fresh()->status);
        $this->assertDatabaseHas('assignment_recipients', ['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'active']);
    }

    public function test_assignment_with_null_due_date_reopens_successfully(): void
    {
        $teacher = $this->teacher();
        $classroom = $this->classroom($teacher);
        $test = $this->testFor($teacher);
        $this->complete($test);
        $assignment = $this->assignment($classroom, $test, ['status' => 'closed', 'published_at' => now()->subDay(), 'closed_at' => now(), 'due_at' => null]);

        $this->actingAs($teacher)->post(route('teacher.assignments.reopen', $assignment))->assertRedirect();
        $this->assertSame('published', $assignment->fresh()->status);
        $this->assertNull($assignment->fresh()->due_at);
    }

    public function test_teacher_can_delete_owned_assignment_soft_delete(): void
    {
        $teacher = $this->teacher();
        $otherTeacher = $this->teacher();
        $classroom = $this->classroom($teacher);
        $test = $this->testFor($teacher);
        $assignment = $this->assignment($classroom, $test, ['status' => 'published', 'published_at' => now(), 'due_at' => null]);

        $this->actingAs($otherTeacher)->delete(route('teacher.assignments.destroy', $assignment))->assertForbidden();
        $this->assertFalse($assignment->fresh()->trashed());

        $this->actingAs($teacher)->delete(route('teacher.assignments.destroy', $assignment))->assertRedirect(route('teacher.classes.show', $classroom));
        $this->assertTrue($assignment->fresh()->trashed());
    }

    public function test_closing_or_deleting_last_published_assignment_unlocks_test(): void
    {
        $teacher = $this->teacher();
        $classroom = $this->classroom($teacher);
        $test = $this->testFor($teacher);
        $this->complete($test);
        $service = app(\App\Services\AssignmentService::class);
        $first = $service->publish($this->assignment($classroom, $test));
        $second = $service->publish($this->assignment($classroom, $test, ['title' => 'Second assignment']));

        $service->close($first);
        $this->assertNotNull($test->fresh()->content_locked_at);

        $this->actingAs($teacher)->delete(route('teacher.assignments.destroy', $second))->assertRedirect();
        $this->assertNull($test->fresh()->content_locked_at);
        $this->assertFalse($test->fresh()->isContentLocked());
    }

    public function test_reopening_assignment_locks_test_again(): void
    {
        $teacher = $this->teacher();
        $classroom = $this->classroom($teacher);
        $test = $this->testFor($teacher);
        $this->complete($test);
        $assignment = $this->assignment($classroom, $test, ['status' => 'closed', 'closed_at' => now()]);

        app(\App\Services\AssignmentService::class)->reopen($assignment);

        $this->assertNotNull($test->fresh()->content_locked_at);
        $this->assertTrue($test->fresh()->isContentLocked());
    }

    public function test_teacher_can_remove_student_from_roster(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $classroom = $this->classroom($teacher);
        $membership = ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'active']);

        $this->actingAs($teacher)->post(route('teacher.memberships.remove', $membership))
            ->assertRedirect();

        $this->assertSame('removed', $membership->fresh()->status);
    }

    public function test_assignment_report_is_scalable_with_many_attempts(): void
    {
        $teacher = $this->teacher();
        $classroom = $this->classroom($teacher);
        $test = $this->testFor($teacher);
        $this->complete($test);
        $assignment = $this->assignment($classroom, $test, ['status' => 'published', 'published_at' => now()]);

        $students = \App\Models\User::factory()->student()->count(20)->create();
        
        $recipientsData = [];
        $testsData = [];
        $answersData = [];
        
        $module = $test->sections->first()->modules->first();
        $question = $module->questions->first();

        foreach ($students as $student) {
            $recipientsData[] = [
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
                'status' => 'active',
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $testUlid = (string) str((string) str()->ulid())->lower();
            $testsData[] = [
                'ulid' => $testUlid,
                'user_id' => $student->id,
                'test_id' => $test->id,
                'assignment_id' => $assignment->id,
                'attempt_number' => 1,
                'status' => 'completed',
                'total_score' => rand(400, 1600),
                'completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            // We'll skip answers to avoid creating multiple DB inserts if not strictly needed
            // since the new query uses SQL aggregation for max score!
        }
        
        \App\Models\AssignmentRecipient::insert($recipientsData);
        \App\Models\UserTest::insert($testsData);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $report = app(\App\Services\AssignmentReportService::class)->build($assignment);
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertLessThan(10, count($queries), 'AssignmentReportService is executing too many queries.');
        $this->assertSame(20, $report['metrics']['assigned']);
    }
}
