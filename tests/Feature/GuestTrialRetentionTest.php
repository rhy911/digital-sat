<?php

namespace Tests\Feature;

use App\Models\Test as SatTest;
use App\Models\User;
use App\Models\UserTest;
use App\Services\ExamSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GuestTrialRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_legacy_deadline_initialization_is_idempotent_and_dry_run_is_read_only(): void
    {
        Carbon::setTestNow('2026-10-27 12:00:00');
        $guest = User::factory()->create(['is_guest' => true]);
        $test = SatTest::create(['title' => 'Legacy attempt', 'test_type' => SatTest::TYPE_MODULE_ONLY, 'status' => 'active', 'is_public' => true]);
        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'is_public_trial' => true,
            'completed_at' => now()->subDays(1),
        ]);
        $attempt->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();

        $this->artisan('public-trial:purge', ['--initialize' => true, '--dry-run' => true])->assertSuccessful();
        $this->assertNull($attempt->fresh()->guest_trial_expires_at);

        $this->artisan('public-trial:purge', ['--initialize' => true])->assertSuccessful();
        $attempt->refresh();
        $this->assertSame('2026-10-27 12:30:00', $attempt->guest_trial_expires_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-27 12:30:00', $attempt->guest_trial_absolute_expires_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-10-27 12:10:00');
        $this->artisan('public-trial:purge', ['--initialize' => true])->assertSuccessful();
        $this->assertSame('2026-10-27 12:30:00', $attempt->fresh()->guest_trial_expires_at->format('Y-m-d H:i:s'));
    }

    public function test_purge_removes_expired_attempt_and_keeps_shared_test_content(): void
    {
        config(['public_trial.retention.cleanup_enabled' => true]);
        $guest = User::factory()->create(['is_guest' => true, 'created_at' => now()->subHours(2)]);
        $test = SatTest::create(['title' => 'Shared test stays', 'test_type' => SatTest::TYPE_MODULE_ONLY, 'status' => 'active', 'is_public' => true]);
        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'status' => 'completed',
            'is_public_trial' => true,
            'guest_trial_expires_at' => now()->subSecond(),
            'guest_trial_absolute_expires_at' => now()->subSecond(),
        ]);

        $this->artisan('public-trial:purge')->assertSuccessful();

        $this->assertDatabaseMissing('user_tests', ['id' => $attempt->id]);
        $this->assertDatabaseHas('tests', ['id' => $test->id]);
    }

    public function test_guest_merge_saves_valid_trials_and_skips_expired_trials(): void
    {
        $guest = User::factory()->create(['is_guest' => true]);
        $account = User::factory()->create(['is_guest' => false]);
        $validTest = SatTest::create(['title' => 'Valid trial', 'test_type' => SatTest::TYPE_MODULE_ONLY, 'status' => 'active', 'is_public' => true]);
        $expiredTest = SatTest::create(['title' => 'Expired trial', 'test_type' => SatTest::TYPE_MODULE_ONLY, 'status' => 'active', 'is_public' => true]);
        $valid = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $validTest->id,
            'status' => 'completed',
            'is_public_trial' => true,
            'guest_trial_expires_at' => now()->addMinutes(10),
            'guest_trial_absolute_expires_at' => now()->addHours(1),
        ]);
        $expired = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $expiredTest->id,
            'status' => 'completed',
            'is_public_trial' => true,
            'guest_trial_expires_at' => now()->subSecond(),
            'guest_trial_absolute_expires_at' => now()->addHours(1),
        ]);

        app(ExamSessionService::class)->mergeGuestAccount($guest->id, $account);

        $this->assertSame($account->id, $valid->fresh()->user_id);
        $this->assertNull($valid->fresh()->guest_trial_expires_at);
        $this->assertNull($valid->fresh()->guest_trial_absolute_expires_at);
        $this->assertTrue($valid->fresh()->is_public_trial);
        $this->assertSame($guest->id, $expired->fresh()->user_id);
    }

    public function test_activity_renewal_clamps_to_the_absolute_deadline_in_utc(): void
    {
        Carbon::setTestNow('2026-10-27 12:00:00');
        $guest = User::factory()->create(['is_guest' => true]);
        $test = SatTest::create(['title' => 'Capped trial', 'test_type' => SatTest::TYPE_MODULE_ONLY, 'status' => 'active', 'is_public' => true]);
        $attempt = UserTest::create([
            'user_id' => $guest->id,
            'test_id' => $test->id,
            'status' => 'in_progress',
            'is_public_trial' => true,
            'guest_trial_expires_at' => now()->addMinutes(20),
            'guest_trial_absolute_expires_at' => now()->addMinutes(5),
        ]);

        $this->assertTrue($attempt->renewGuestTrialActivity());
        $attempt->refresh();

        $this->assertSame(now()->addMinutes(5)->timestamp, $attempt->guest_trial_expires_at->timestamp);
        $rawDeadline = Carbon::createFromFormat('Y-m-d H:i:s', $attempt->getRawOriginal('guest_trial_expires_at'), 'UTC');
        $this->assertSame(now()->addMinutes(5)->timestamp, $rawDeadline->timestamp);
    }
}
