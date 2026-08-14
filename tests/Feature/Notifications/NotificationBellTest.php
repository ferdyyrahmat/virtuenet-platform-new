<?php

namespace Tests\Feature\Notifications;

use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_fetch_notifications_with_unread_count(): void
    {
        $user = User::factory()->create();

        SystemNotification::send($user, 'Title A', 'Msg A');
        SystemNotification::send($user, 'Title B', 'Msg B');
        SystemNotification::create([
            'user_id' => $user->id,
            'title' => 'Read',
            'message' => 'x',
            'type' => 'info',
            'is_read' => true,
            'read_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('notifications.bell.index'))
            ->assertOk();

        $this->assertSame(2, $response->json('unreadCount'));
        $this->assertCount(3, $response->json('notifications'));
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = SystemNotification::send($user, 'Unread', 'Msg');

        $this->actingAs($user)
            ->post(route('notifications.bell.read', $notification->id))
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('system_notifications', ['id' => $notification->id, 'is_read' => true]);
    }

    public function test_user_cannot_act_on_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = SystemNotification::send($owner, 'Private', 'Msg');

        $this->actingAs($other)
            ->post(route('notifications.bell.read', $notification->id))
            ->assertNotFound();

        $this->actingAs($other)
            ->delete(route('notifications.bell.destroy', $notification->id))
            ->assertNotFound();
    }

    public function test_user_can_delete_notification_and_clear_all(): void
    {
        $user = User::factory()->create();
        $first = SystemNotification::send($user, 'A', 'Msg');
        SystemNotification::send($user, 'B', 'Msg');

        $this->actingAs($user)
            ->delete(route('notifications.bell.destroy', $first->id))
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('system_notifications', ['id' => $first->id]);

        $this->actingAs($user)
            ->post(route('notifications.bell.clear'))
            ->assertJson(['success' => true]);

        $this->assertSame(0, SystemNotification::where('user_id', $user->id)->count());
    }
}
