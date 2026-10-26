<?php

namespace Tests\Feature;

use App\Models\AnswerChoice;
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
use App\Services\TestManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ScoringFormBuilder;
use Tests\TestCase;

class FullTestScoringModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_generators_create_four_module_normal_and_six_module_adaptive_forms(): void
    {
        $service = app(TestManagementService::class);
        $normal = $service->generateFullSatStructure('Normal', Test::TYPE_FULL);
        $adaptive = $service->generateFullSatStructure('Adaptive', Test::TYPE_ADAPTIVE_FULL);

        $this->assertSame(4, $normal->sections->sum(fn ($section) => $section->modules->count()));
        $this->assertSame(6, $adaptive->sections->sum(fn ($section) => $section->modules->count()));
        $this->assertTrue($normal->sections->every(fn ($section) => $section->modules->where('module_number', 2)->count() === 1));
        $this->assertTrue($adaptive->sections->every(fn ($section) => $section->modules->where('module_number', 2)->count() === 2));
    }

    public function test_incomplete_adaptive_draft_can_convert_to_normal_but_cannot_publish_as_adaptive(): void
    {
        $teacher = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $test = Test::create(['title' => 'One Path', 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'draft', 'created_by' => $teacher->id]);
        foreach ([[Section::TYPE_RW, 1], [Section::TYPE_MATH, 2]] as [$type, $order]) {
            $section = Section::create(['test_id' => $test->id, 'name' => $type, 'type' => $type, 'order' => $order, 'created_by' => $teacher->id]);
            foreach ([[1, 'standard'], [2, 'hard']] as $moduleOrder => [$number, $difficulty]) {
                $module = Module::create(['section_id' => $section->id, 'module_number' => $number, 'difficulty_level' => $difficulty, 'duration_minutes' => 10, 'total_questions' => 1, 'order' => $moduleOrder + 1, 'created_by' => $teacher->id]);
                $this->question($module, $type);
            }
        }

        $this->actingAs($teacher)->putJson(route('home-dashboard.tests.update', $test), [
            'title' => $test->title, 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors('test_structure');

        $this->actingAs($teacher)->postJson(route('home-dashboard.tests.convert-to-normal', $test))
            ->assertOk()->assertJsonPath('data.test_type', Test::TYPE_FULL);
    }

    public function test_normal_full_cannot_publish_with_non_official_module_sizes(): void
    {
        $teacher = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $test = Test::create(['title' => 'Undersized Normal', 'test_type' => Test::TYPE_FULL, 'status' => 'draft', 'created_by' => $teacher->id]);
        foreach ([[Section::TYPE_RW, 1], [Section::TYPE_MATH, 2]] as [$type, $order]) {
            $section = Section::create(['test_id' => $test->id, 'name' => $type, 'type' => $type, 'order' => $order, 'created_by' => $teacher->id]);
            foreach ([1, 2] as $moduleNumber) {
                $module = Module::create([
                    'section_id' => $section->id,
                    'module_number' => $moduleNumber,
                    'difficulty_level' => $moduleNumber === 1 ? 'standard' : 'hard',
                    'duration_minutes' => $type === Section::TYPE_RW ? Module::RW_DURATION : Module::MATH_DURATION,
                    'total_questions' => 1,
                    'order' => $moduleNumber,
                    'created_by' => $teacher->id,
                ]);
                $this->question($module, $type);
            }
        }

        $this->actingAs($teacher)->putJson(route('home-dashboard.tests.update', $test), ['status' => 'active'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('test_structure');

        $this->assertSame('draft', $test->fresh()->status);
    }

    public function test_scoring_type_cannot_change_after_attempts_exist(): void
    {
        $teacher = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $test = Test::create(['title' => 'Attempted Form', 'test_type' => Test::TYPE_FULL, 'status' => 'draft', 'created_by' => $teacher->id]);
        UserTest::create(['user_id' => $student->id, 'test_id' => $test->id, 'status' => 'in_progress']);

        $this->actingAs($teacher)->putJson(route('home-dashboard.tests.update', $test), ['test_type' => Test::TYPE_ADAPTIVE_FULL])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('test_type');

        $this->assertSame(Test::TYPE_FULL, $test->fresh()->test_type);
    }

    public function test_adaptive_draft_cannot_convert_to_normal_after_attempts_exist(): void
    {
        $teacher = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $test = Test::create(['title' => 'Attempted Adaptive', 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'draft', 'created_by' => $teacher->id]);
        UserTest::create(['user_id' => $student->id, 'test_id' => $test->id, 'status' => 'in_progress']);

        $this->actingAs($teacher)->postJson(route('home-dashboard.tests.convert-to-normal', $test))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('test_type');

        $this->assertSame(Test::TYPE_ADAPTIVE_FULL, $test->fresh()->test_type);
    }

    public function test_adaptive_result_discloses_provisional_range(): void
    {
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $test = Test::create(['title' => 'IRT Result', 'test_type' => Test::TYPE_ADAPTIVE_FULL, 'status' => 'active']);
        $attempt = UserTest::create([
            'user_id' => $student->id, 'test_id' => $test->id, 'status' => 'completed', 'completed_at' => now(),
            'total_score' => 1240, 'total_score_lower' => 1170, 'total_score_upper' => 1310,
            'score_reading_writing' => 610, 'score_math' => 630,
            'rw_m2_path' => 'hard', 'math_m2_path' => 'hard',
            'score_conversion_version' => 'provisional_irt_v1', 'score_estimate_kind' => 'adaptive_irt_provisional',
        ]);

        $this->actingAs($student)->get(route('student.scores.show', $attempt))
            ->assertOk()->assertSee('Estimated practice score')->assertSee('1170 and 1310')->assertSee('The scaled score mapping for this route is still provisional');
    }

    public function test_rescore_command_audits_and_corrects_generic_v1_attempt(): void
    {
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $test = ScoringFormBuilder::normalFull('Legacy complete form');
        $attempt = ScoringFormBuilder::play($test, $student, fn (Module $module) => $module->questions->where('is_pretest', false)->count());
        $this->assertSame(800, $attempt->score_reading_writing);
        $this->assertSame(800, $attempt->score_math);
        $this->assertSame(0, $attempt->userAnswers()->whereNull('question_snapshot')->count());
        $attempt->update([
            'score_reading_writing' => 710, 'score_math' => 700, 'total_score' => 1410,
            'score_conversion_version' => 'generic_ds_v1', 'score_estimate_kind' => 'generic',
        ]);
        $snapshot = $attempt->userAnswers()->firstOrFail()->question_snapshot;
        $this->assertIsArray($snapshot);
        $this->assertNotEmpty($snapshot);
        $expected = app(\App\Services\SatScoringPipeline::class)->rescoreLegacy($attempt->fresh());
        $this->assertSame('ready', $expected['status'], $expected['reason'] ?? '');
        $this->assertSame(800, $expected['score_reading_writing']);
        $this->assertSame(800, $expected['score_math']);
        $attempt->userAnswers()->update(['is_correct' => false]);
        $zero = app(\App\Services\SatScoringPipeline::class)->rescoreLegacy($attempt->fresh());
        $this->assertSame(200, $zero['score_reading_writing']);
        $this->assertSame(200, $zero['score_math']);
        $attempt->userAnswers()->update(['is_correct' => true]);
 
        $this->artisan('sat:rescore-generic-v1')->assertSuccessful();
        $this->assertSame(1410, $attempt->fresh()->total_score);
        $this->artisan('sat:rescore-generic-v1', ['--apply' => true])->assertSuccessful();
 
        $attempt->refresh();
        $this->assertSame($expected['total_score'], $attempt->total_score);
        $this->assertSame('normal_consensus_v1', $attempt->score_conversion_version);
        $this->assertDatabaseHas('user_test_score_revisions', ['user_test_id' => $attempt->id]);
        $this->assertSame(1410, $attempt->scoreRevisions()->firstOrFail()->previous_score['total_score']);
        $this->assertSame(1, $attempt->scoreRevisions()->count());
    }

    public function test_safe_preview_rejects_wrong_membership_and_incomplete_snapshot_shapes(): void
    {
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $test = ScoringFormBuilder::normalFull('Historical validation form');
        $attempt = ScoringFormBuilder::play($test, $student, fn (Module $module) => 0);
        $this->assertSame(200, $attempt->score_reading_writing);
        $this->assertSame(200, $attempt->score_math);
        $attempt->update(['score_conversion_version' => 'generic_ds_v1', 'total_score' => 990]);
        $revisionCount = $attempt->scoreRevisions()->count();
        $service = app(\App\Services\SatScoringPipeline::class);
        $answer = $attempt->userAnswers()->firstOrFail();
        $validSnapshot = $answer->question_snapshot;
        $validQuestionId = $answer->question_id;

        foreach ([
            [null, 'historical_question_snapshot_missing_or_malformed'],
            [[], 'historical_question_snapshot_missing_or_malformed'],
            [['stem' => 'partial', 'question_type' => 'multiple_choice', 'section_type' => Section::TYPE_RW], 'historical_question_snapshot_incomplete'],
        ] as [$snapshot, $reason]) {
            $answer->update(['question_snapshot' => $snapshot]);
            $result = $service->rescoreLegacy($attempt->fresh());
            $this->assertSame('skipped', $result['status']);
            $this->assertSame($reason, $result['reason']);
        }
        $answer->update(['question_snapshot' => $validSnapshot]);

        $rogueQuestion = Question::create(['stem' => 'Not on the form', 'question_type' => Question::TYPE_MCQ, 'difficulty' => 'medium', 'section_type' => Section::TYPE_RW, 'skill_domain' => 'fixture', 'is_complete' => true]);
        $answer->update(['question_id' => $rogueQuestion->id]);
        $wrongSet = $service->rescoreLegacy($attempt->fresh());
        $this->assertSame('skipped', $wrongSet['status']);
        $this->assertSame('incomplete_or_unexpected_module_responses', $wrongSet['reason']);
        $answer->update(['question_id' => $validQuestionId]);

        $attempt->userAnswers()->where('id', '<>', $answer->id)->firstOrFail()->delete();
        $missing = $service->rescoreLegacy($attempt->fresh());
        $this->assertSame('skipped', $missing['status']);
        $this->assertSame('incomplete_or_unexpected_module_responses', $missing['reason']);
        $this->assertSame($revisionCount, $attempt->scoreRevisions()->count());
        $this->assertSame(990, $attempt->fresh()->total_score);
    }

    public function test_adaptive_comparison_requires_routes_and_every_presented_module(): void
    {
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $test = ScoringFormBuilder::adaptiveFull('Adaptive historical form');
        $attempt = ScoringFormBuilder::play($test, $student, fn (Module $module) => 0);
        $this->assertSame(200, $attempt->score_reading_writing);
        $this->assertSame(200, $attempt->score_math);
        $service = app(\App\Services\SatScoringPipeline::class);

        $this->assertSame('compared', $service->candidateComparison($attempt)['status']);
        $path = $attempt->rw_m2_path;
        $attempt->update(['rw_m2_path' => null]);
        $missingRoute = $service->candidateComparison($attempt->fresh());
        $this->assertSame('skipped', $missingRoute['status']);
        $this->assertSame('missing_or_invalid_adaptive_route', $missingRoute['reason']);
        $attempt->update(['rw_m2_path' => $path]);

        $selectedModule = $test->sections->firstWhere('type', Section::TYPE_RW)->modules
            ->first(fn ($module) => (int) $module->module_number === 2 && $module->difficulty_level === $path);
        $otherPath = $path === Module::DIFFICULTY_EASY ? Module::DIFFICULTY_HARD : Module::DIFFICULTY_EASY;
        $unpresentedModule = $test->sections->firstWhere('type', Section::TYPE_RW)->modules
            ->first(fn ($module) => (int) $module->module_number === 2 && $module->difficulty_level === $otherPath);
        $unpresentedQuestion = $unpresentedModule->questions->first();
        $extraAnswer = $attempt->userAnswers()->create([
            'module_id' => $unpresentedModule->id,
            'question_id' => $unpresentedQuestion->id,
            'selected_answer' => 'A',
            'is_correct' => true,
            'question_snapshot' => [
                'stem' => $unpresentedQuestion->stem, 'question_type' => $unpresentedQuestion->question_type,
                'section_type' => Section::TYPE_RW, 'is_pretest' => (bool) $unpresentedQuestion->is_pretest,
                'irt_a' => $unpresentedQuestion->irt_a, 'irt_b' => $unpresentedQuestion->irt_b, 'irt_c' => $unpresentedQuestion->irt_c,
            ],
        ]);
        $wrongBranch = $service->candidateComparison($attempt->fresh());
        $this->assertSame('skipped', $wrongBranch['status']);
        $this->assertSame('response_for_unpresented_or_wrong_branch_module', $wrongBranch['reason']);
        $extraAnswer->delete();

        $attempt->userAnswers()->where('module_id', $selectedModule->id)->firstOrFail()->delete();
        $missingModuleResponse = $service->candidateComparison($attempt->fresh());
        $this->assertSame('skipped', $missingModuleResponse['status']);
        $this->assertSame('incomplete_or_unexpected_module_responses', $missingModuleResponse['reason']);

        $this->assertSame(0, $attempt->scoreRevisions()->count());
    }

    public function test_score_candidate_audit_is_read_only_and_reports_skips(): void
    {
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $teacher = User::factory()->teacher()->create(['email_verified_at' => now()]);
        $test = Test::create(['title' => 'Unsupported audit form', 'test_type' => 'custom_test', 'status' => 'active']);
        $test->update(['created_by' => $teacher->id]);
        $attempt = UserTest::create([
            'user_id' => $student->id, 'test_id' => $test->id, 'status' => 'completed',
            'completed_at' => now(), 'total_score' => 1000,
        ]);
        $classroom = Classroom::create(['owner_id' => $teacher->id, 'name' => 'Audit remains read-only']);
        ClassroomMembership::create(['classroom_id' => $classroom->id, 'student_id' => $student->id, 'status' => 'active']);
        $assignment = Assignment::create([
            'classroom_id' => $classroom->id, 'teacher_id' => $teacher->id, 'test_id' => $test->id,
            'title' => 'Unchanged assignment', 'status' => 'published',
        ]);
        AssignmentRecipient::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'active', 'assigned_at' => now()]);
        $before = [
            'attempt' => $attempt->fresh()->toArray(), 'assignments' => Assignment::count(),
            'recipients' => AssignmentRecipient::count(), 'revisions' => $attempt->scoreRevisions()->count(),
        ];
        $path = tempnam(sys_get_temp_dir(), 'score-audit-');

        try {
            $this->artisan('sat:scores-audit', ['--output' => $path])->assertSuccessful();
            $csv = file_get_contents($path);
            $this->assertStringContainsString('mle_candidate_v2_total', $csv);
            $this->assertStringContainsString('unsupported_test_type', $csv);
            $this->assertSame($before, [
                'attempt' => $attempt->fresh()->toArray(), 'assignments' => Assignment::count(),
                'recipients' => AssignmentRecipient::count(), 'revisions' => $attempt->scoreRevisions()->count(),
            ]);
        } finally {
            @unlink($path);
        }
    }

    public function test_rescore_preview_skips_legacy_attempts_without_complete_snapshots(): void
    {
        $student = User::factory()->student()->create(['email_verified_at' => now()]);
        $test = ScoringFormBuilder::normalFull('Legacy incomplete snapshots');
        $attempt = ScoringFormBuilder::play($test, $student, fn (Module $module) => 0);
        $attempt->update(['score_conversion_version' => 'generic_ds_v1', 'total_score' => 800]);
        $attempt->userAnswers()->firstOrFail()->update(['question_snapshot' => null]);

        $before = $attempt->fresh()->only(['total_score', 'score_conversion_version']);
        $preview = app(\App\Services\SatScoringPipeline::class)->rescoreLegacy($attempt->fresh());

        $this->assertSame('skipped', $preview['status']);
        $this->assertSame('historical_question_snapshot_missing_or_malformed', $preview['reason']);
        $this->assertSame($before, $attempt->fresh()->only(['total_score', 'score_conversion_version']));
        $this->assertSame(0, $attempt->scoreRevisions()->count());
    }

    private function question(Module $module, string $type): Question
    {
        $question = Question::create(['stem' => 'Fixture', 'question_type' => Question::TYPE_MCQ, 'difficulty' => 'medium', 'section_type' => $type, 'skill_domain' => 'fixture', 'is_complete' => true]);
        foreach (['A', 'B', 'C', 'D'] as $index => $label) {
            AnswerChoice::create(['question_id' => $question->id, 'label' => $label, 'content' => $label, 'is_correct' => $index === 0, 'order' => $index + 1]);
        }
        $module->questions()->attach($question->id, ['position' => 1]);

        return $question;
    }

}
