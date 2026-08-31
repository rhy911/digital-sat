<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Question;
use App\Models\Section;
use App\Models\Test as TestModel;
use App\Models\User;
use App\Services\RecycleBinService;
use App\Services\TestManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecycleBinTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_cloned_test_does_not_soft_delete_shared_questions(): void
    {
        $management = app(TestManagementService::class);
        $original = $management->generateFullSatStructure('Original', TestModel::TYPE_FULL);
        $module = $original->sections->first()->modules->first();
        $question = Question::create([
            'section_type' => Section::TYPE_RW,
            'stem' => 'Shared question',
            'question_type' => Question::TYPE_MCQ,
            'skill_domain' => 'information_and_ideas',
            'is_complete' => true,
        ]);
        $module->questions()->attach($question->id, ['position' => 1]);
        $clone = $management->cloneTest($original->id);

        $management->deleteTest($clone->id, true);

        $this->assertDatabaseHas('questions', ['id' => $question->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('tests', ['id' => $clone->id]);
        $this->assertSoftDeleted('sections', ['test_id' => $clone->id]);
    }

    public function test_recycle_bin_keeps_recent_deletions_and_purges_after_seven_days(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');
        $test = TestModel::create(['title' => 'Recent deletion', 'test_type' => TestModel::TYPE_FULL]);
        $test->delete();

        $this->artisan('recycle-bin:purge')->assertSuccessful();
        $this->assertSoftDeleted('tests', ['id' => $test->id]);

        $test->deleted_at = now()->subDays(8);
        $test->save();

        app(RecycleBinService::class)->purgeExpired();

        $this->assertDatabaseMissing('tests', ['id' => $test->id]);
        Carbon::setTestNow();
    }

    public function test_a_trashed_item_can_be_permanently_deleted_individually(): void
    {
        $test = TestModel::create(['title' => 'Delete forever', 'test_type' => TestModel::TYPE_FULL]);
        $test->delete();

        $admin = new User;
        $admin->role = 'admin';
        $result = app(RecycleBinService::class)->permanentlyDeleteItem('test', $test->id, $admin);

        $this->assertSame(1, $result['purged']);
        $this->assertDatabaseMissing('tests', ['id' => $test->id]);
    }

    public function test_emptying_recycle_bin_permanently_deletes_visible_items(): void
    {
        $test = TestModel::create(['title' => 'Clear all', 'test_type' => TestModel::TYPE_FULL]);
        $test->delete();

        $admin = new User;
        $admin->role = 'admin';
        $result = app(RecycleBinService::class)->permanentlyDeleteAll($admin);

        $this->assertSame(1, $result['purged']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseMissing('tests', ['id' => $test->id]);
    }
}
