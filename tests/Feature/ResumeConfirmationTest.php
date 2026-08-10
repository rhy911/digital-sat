<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Section;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Resume entry points used to link straight into the engine, which starts the
 * module timer on load. Every one of them now goes through a confirmation
 * first: the home hero opens the shared readiness modal, the library's resume
 * card opens the same "Resume or start fresh?" modal its test card does.
 */
class ResumeConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function inProgressAttempt(User $student, array $extra = []): UserTest
    {
        $test = Test::create([
            'title' => 'Adaptive Full Practice Test 1',
            'test_type' => 'full_length',
            'break_duration_minutes' => 0,
            'status' => 'active',
            'is_public' => true,
        ]);
        $section = Section::create([
            'test_id' => $test->id,
            'name' => 'Reading and Writing',
            'type' => 'reading_writing',
            'order' => 1,
        ]);
        $module = Module::create([
            'section_id' => $section->id,
            'module_number' => 1,
            'difficulty_level' => 'standard',
            'duration_minutes' => 32,
            'total_questions' => 1,
            'order' => 1,
        ]);

        return UserTest::create(array_merge([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
            'current_module_id' => $module->id,
        ], $extra));
    }

    public function test_home_arms_the_readiness_modal_for_an_in_progress_attempt(): void
    {
        $student = User::factory()->student()->create();
        $attempt = $this->inProgressAttempt($student);

        $this->actingAs($student)->get('/home')
            ->assertOk()
            // Js::from() escapes slashes and non-ASCII, so assert on the pieces
            // that survive JSON encoding rather than on the raw URL.
            ->assertSee('open-ready-modal')
            ->assertSee('Ready to Resume Attempt?')
            ->assertSee($attempt->ulid)
            ->assertSee('resume');
    }

    public function test_library_resume_card_opens_the_attempt_modal_instead_of_the_engine(): void
    {
        $student = User::factory()->student()->create();
        $attempt = $this->inProgressAttempt($student);
        $module = $attempt->currentModule;

        $response = $this->actingAs($student)->get('/student/practice')->assertOk();

        preg_match(
            '/library-resume-card__action.*?<\/div>/s',
            $response->getContent(),
            $action
        );
        $this->assertNotEmpty($action, 'Resume card action block not rendered.');
        $this->assertStringContainsString('<button', $action[0]);
        $this->assertStringContainsString('data-test-id="'.$attempt->test_id.'"', $action[0]);

        $response->assertDontSee(
            route('engine.session', ['ulid' => $module->ulid]).'?attempt='.$attempt->ulid,
            false
        );
    }

    public function test_library_resume_card_keeps_the_direct_link_for_an_assignment_attempt(): void
    {
        $student = User::factory()->student()->create();
        $teacher = User::factory()->teacher()->create();
        $attempt = $this->inProgressAttempt($student);

        $classroom = \App\Models\Classroom::create([
            'owner_id' => $teacher->id,
            'name' => 'Resume Cohort',
            'status' => 'active',
        ]);
        $assignment = \App\Models\Assignment::create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'test_id' => $attempt->test_id,
            'title' => 'Assigned Practice',
            'attempt_limit' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $attempt->forceFill(['assignment_id' => $assignment->id])->save();

        // attempt-options only resolves self-study attempts, so routing an
        // assignment attempt through that modal would offer a fresh practice
        // attempt instead of resuming the assigned one.
        $this->actingAs($student)->get('/student/practice')
            ->assertOk()
            ->assertSee(
                route('engine.session', ['ulid' => $attempt->currentModule->ulid]).'?attempt='.$attempt->ulid,
                false
            );
    }
}
