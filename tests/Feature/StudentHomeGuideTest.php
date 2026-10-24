<?php

namespace Tests\Feature;

use App\Http\Middleware\RecordHomeOpening;
use App\Models\Test;
use App\Models\User;
use App\Models\UserTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class StudentHomeGuideTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(User $user, array $attributes = []): UserTest
    {
        $test = Test::create(['title' => 'Guide practice', 'test_type' => 'full_length', 'status' => 'active', 'is_public' => true]);
        return UserTest::create($attributes + ['user_id' => $user->id, 'test_id' => $test->id, 'status' => 'completed', 'attempt_type' => 'full', 'total_score' => 1200, 'score_reading_writing' => 600, 'score_math' => 600, 'completed_at' => now()]);
    }

    private function opening(User $user, string $name, array $parameters = [], int $status = 200): void
    {
        $request = Request::create('/tracking-test');
        $route = (new Route('GET', '/tracking-test', fn () => null))->name($name);
        $route->bind($request);
        foreach ($parameters as $key => $value) $route->setParameter($key, $value);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user->fresh());
        (new RecordHomeOpening)->handle($request, fn () => response('Rendered page', $status));
    }

    public function test_preview_requires_successful_engine_visit_and_is_idempotent(): void
    {
        $student = User::factory()->student()->create();
        $this->opening($student, 'test.preview');
        $this->opening($student, 'engine.session', ['ulid' => 'preview-rw'], 302);
        $this->opening($student, 'engine.session', ['ulid' => 'preview-math'], 500);
        $this->assertNull($student->fresh()->home_preview_opened_at);
        $this->opening($student, 'engine.session', ['ulid' => 'preview-rw']);
        $first = $student->fresh()->home_preview_opened_at;
        $this->assertNotNull($first);
        $this->travel(1)->day();
        $this->opening($student, 'engine.session', ['ulid' => 'preview-math']);
        $this->assertTrue($first->equalTo($student->fresh()->home_preview_opened_at));
        $this->assertContains(RecordHomeOpening::class, app('router')->getRoutes()->getByName('engine.session')->gatherMiddleware());
    }

    public function test_report_marker_requires_owned_completed_non_preview_non_absorbed_report(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $own = $this->attempt($student);
        $foreign = $this->attempt($other);
        $active = $this->attempt($student, ['status' => 'in_progress']);
        $preview = $this->attempt($student);
        $preview->test->update(['title' => 'Test Preview']);
        $absorbed = $this->attempt($student, ['attempt_type' => 'section', 'section_type' => 'math']);
        UserTest::create(['user_id' => $student->id, 'test_id' => $absorbed->test_id, 'status' => 'completed', 'attempt_type' => 'full', 'score_math' => 600, 'score_reading_writing' => 600, 'completed_at' => now()->addMinute()]);
        foreach ([$foreign, $active, $preview, $absorbed] as $attempt) $this->opening($student, 'student.scores.show', ['userTest' => $attempt]);
        $this->opening($student, 'student.scores.export-pdf', ['userTest' => $own]);
        $this->opening($student, 'student.scores.show', ['userTest' => $own], 403);
        $this->assertNull($student->fresh()->home_report_opened_at);
        $this->opening($student, 'student.scores.show', ['userTest' => $own]);
        $this->assertNotNull($student->fresh()->home_report_opened_at);
        $teacher = User::factory()->teacher()->create();
        $this->opening($teacher, 'student.scores.show', ['userTest' => $own]);
        $this->opening($teacher, 'engine.session', ['ulid' => 'preview-rw']);
        $this->assertNull($teacher->fresh()->home_report_opened_at);
        $this->assertNull($teacher->fresh()->home_preview_opened_at);
        $this->assertContains(RecordHomeOpening::class, app('router')->getRoutes()->getByName('student.scores.show')->gatherMiddleware());
    }

    public function test_legacy_account_progress_requires_report_visit_and_optional_preview_goal_never_block(): void
    {
        $student = User::factory()->student()->create(['target_score' => null]);
        $attempt = $this->attempt($student, ['attempt_type' => 'section', 'section_type' => 'math']);
        $this->actingAs($student)->get('/home')->assertOk()->assertSee('Open your score report')->assertViewHas('homeGuide', fn ($guide) => $guide['practiceCompleted'] && !$guide['reportOpened'] && !$guide['previewOpened'] && $guide['expanded']);
        $this->opening($student, 'student.scores.show', ['userTest' => $attempt]);
        $attempt->delete();
        $this->actingAs($student->fresh())->get('/home')->assertOk()->assertSee('Browse practice tests')->assertViewHas('homeGuide', fn ($guide) => $guide['practiceCompleted'] && $guide['reportOpened'] && !$guide['expanded']);
    }

    public function test_preference_is_persisted_validated_and_student_scoped(): void
    {
        $student = User::factory()->student()->create();
        $student->forceFill(['home_report_opened_at' => now()])->save();
        $this->actingAs($student)->patchJson('/student/home/guide', ['preference' => 'expanded'])->assertOk()->assertJsonPath('preference', 'expanded');
        $this->actingAs($student->fresh())->get('/home')->assertViewHas('homeGuide', fn ($guide) => $guide['expanded']);
        $this->patchJson('/student/home/guide', ['preference' => 'auto'])->assertUnprocessable();
        $this->patchJson('/student/home/guide', ['preference' => 'hidden'])->assertOk();
        $this->actingAs($student->fresh())->get('/home')->assertViewHas('homeGuide', fn ($guide) => !$guide['expanded']);
        $this->actingAs(User::factory()->teacher()->create())->patchJson('/student/home/guide', ['preference' => 'expanded'])->assertForbidden();
        $this->actingAs(User::factory()->student()->unverified()->create())->patchJson('/student/home/guide', ['preference' => 'expanded'])->assertRedirect(route('verify.email.notice'));
    }

    public function test_preview_opening_advances_to_first_practice_without_completing_essential_steps(): void
    {
        $student = User::factory()->student()->create();
        $this->opening($student, 'engine.session', ['ulid' => 'preview-rw']);
        $this->actingAs($student->fresh())->get('/home')->assertOk()->assertSee('Choose first practice')->assertSee('0 of 2 essential steps complete')->assertSee('Opened');
    }

    public function test_actual_successful_study_pages_record_milestones_after_rendering(): void
    {
        $student = User::factory()->student()->create();
        $attempt = $this->attempt($student);
        $this->actingAs($student)->get(route('test.preview'))->assertOk();
        $this->assertNull($student->fresh()->home_preview_opened_at);
        $this->get(route('engine.session', ['ulid' => 'preview-rw']))->assertOk();
        $this->assertNotNull($student->fresh()->home_preview_opened_at);
        $this->get(route('student.scores.show', $attempt))->assertOk();
        $this->assertNotNull($student->fresh()->home_report_opened_at);
    }

    public function test_weekday_strip_uses_real_vietnam_completion_dates(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-23 12:00:00', 'Asia/Ho_Chi_Minh'));
        $student = User::factory()->student()->create();
        $this->attempt($student, ['completed_at' => '2026-10-19 00:00:00']);
        $this->attempt($student, ['completed_at' => '2026-10-23 10:00:00']);
        $this->actingAs($student)->get('/home')->assertOk()->assertViewHas('weeklyActivity', fn ($activity) => $activity['days'][0]['completed'] && $activity['days'][4]['completed'] && $activity['days'][4]['today'] && !$activity['days'][3]['completed']);
    }
}
