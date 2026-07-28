<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function makeNotification(User $user, array $data = []): string
    {
        $id = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $id,
            'type' => 'App\\Notifications\\AssignmentPublishedNotification',
            'data' => array_merge([
                'type' => 'assignment_published',
                'title' => 'New assignment',
                'body' => 'Algebra I: Quiz 1',
                'url' => '/student/assignments/abc',
                'icon' => 'assignment',
            ], $data),
        ]);

        return $id;
    }

    public function test_summary_returns_unread_count_and_recent(): void
    {
        $user = User::factory()->student()->create();
        $this->makeNotification($user);

        $response = $this->actingAs($user)->getJson(route('notifications.summary'));

        $response->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('recent.0.title', 'New assignment')
            ->assertJsonPath('recent.0.read', false);
    }

    public function test_index_page_lists_notifications(): void
    {
        $user = User::factory()->student()->create();
        $this->makeNotification($user);

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Algebra I: Quiz 1');
    }

    public function test_mark_all_read_clears_unread(): void
    {
        $user = User::factory()->student()->create();
        $this->makeNotification($user);
        $this->makeNotification($user);

        $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_mark_single_read(): void
    {
        $user = User::factory()->student()->create();
        $id = $this->makeNotification($user);

        $this->actingAs($user)->postJson(route('notifications.read', $id))->assertOk();

        $this->assertNotNull($user->notifications()->find($id)->read_at);
    }

    public function test_cannot_mark_another_users_notification(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $id = $this->makeNotification($owner);

        $this->actingAs($other)->postJson(route('notifications.read', $id))->assertNotFound();

        $this->assertNull($owner->notifications()->find($id)->read_at);
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->getJson(route('notifications.summary'))->assertUnauthorized();
    }
}
