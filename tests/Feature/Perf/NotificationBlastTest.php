<?php

namespace Tests\Feature\Perf;

use App\Jobs\SendNotificationBlastJob;
use App\Models\NotificationBlast;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationBlastTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithBlastPermission(): User
    {
        $role = Role::firstOrCreate(['name' => Role::ADMIN, 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'admin.notifications.send-blast', 'guard_name' => 'web']));

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_send_blast_dispatches_job_and_marks_blast_queued(): void
    {
        Queue::fake();

        $admin = $this->adminWithBlastPermission();
        User::factory()->count(3)->create();

        $this->actingAs($admin)
            ->post(route('admin.notifications.send-blast'), [
                'title' => 'Maintenance notice',
                'message' => 'System will be down at 00:00.',
                'target_type' => 'all',
                'type' => 'warning',
            ])
            ->assertJson(['success' => true]);

        Queue::assertPushed(SendNotificationBlastJob::class);

        $this->assertDatabaseHas('notification_blasts', [
            'status' => 'queued',
            'target_type' => 'all',
            'sent_count' => 0,
            'failed_count' => 0,
        ]);
    }

    public function test_blast_job_creates_notifications_and_marks_blast_sent(): void
    {
        $admin = $this->adminWithBlastPermission();
        $users = User::factory()->count(3)->create();
        $userId = $admin->id;

        $this->actingAs($admin)
            ->post(route('admin.notifications.send-blast'), [
                'title' => 'Holiday announcement',
                'message' => 'Enjoy the long weekend!',
                'target_type' => 'all',
                'type' => 'success',
            ])
            ->assertJson(['success' => true]);

        $blast = NotificationBlast::where('title', 'Holiday announcement')->firstOrFail();

        $this->assertSame('sent', $blast->status);
        $this->assertSame(4, $blast->sent_count);
        $this->assertSame(0, $blast->failed_count);
        $this->assertSame(4, SystemNotification::where('title', 'Holiday announcement')->whereIn('user_id', $users->pluck('id')->push($userId)->all())->count());
    }

    public function test_send_blast_to_role_target_only_dispatches_to_role_members(): void
    {
        Queue::fake();

        $admin = $this->adminWithBlastPermission();
        $targetRole = Role::firstOrCreate(['name' => Role::USER, 'guard_name' => 'web']);

        $member = User::factory()->create();
        $member->assignRole($targetRole);

        $outsider = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.notifications.send-blast'), [
                'title' => 'Role only',
                'message' => 'Only role members should get this.',
                'target_type' => 'role',
                'target_id' => $targetRole->id,
                'type' => 'info',
            ])
            ->assertJson(['success' => true]);

        Queue::assertPushed(SendNotificationBlastJob::class, function (SendNotificationBlastJob $job) use ($member, $outsider) {
            return in_array($member->id, $job->userIds, true) && ! in_array($outsider->id, $job->userIds, true);
        });
    }
}