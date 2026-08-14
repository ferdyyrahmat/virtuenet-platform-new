<?php

namespace Tests\Feature\Feature;

use App\Models\Feedback;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithPermission(string $permissionName): User
    {
        $role = Role::firstOrCreate(['name' => Role::ADMIN, 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeFeedback(User $submitter): Feedback
    {
        return Feedback::create([
            'user_id'  => $submitter->id,
            'name'     => $submitter->name,
            'email'    => $submitter->email,
            'subject'  => 'Dark mode contrast',
            'category' => 'feature_request',
            'message'  => 'Please improve contrast.',
            'rating'   => 5,
            'status'   => 'pending',
        ]);
    }

    public function test_authenticated_user_can_submit_feedback(): void
    {
        $admin = $this->staffWithPermission('admin.feedbacks.store');

        $this->actingAs($admin)
            ->post(route('admin.feedbacks.store'), [
                'subject'  => 'Dark mode contrast',
                'category' => 'feature_request',
                'message'  => 'Please improve contrast.',
                'rating'   => 5,
            ])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('feedbacks', [
            'subject'  => 'Dark mode contrast',
            'status'   => 'pending',
            'user_id'  => $admin->id,
            'name'     => $admin->name,
            'email'    => $admin->email,
        ]);
    }

    public function test_admin_can_view_feedback_index(): void
    {
        $admin = $this->staffWithPermission('admin.feedbacks.index');
        $this->makeFeedback($admin);

        $this->actingAs($admin)
            ->get(route('admin.feedbacks.index'))
            ->assertOk()
            ->assertViewHas('stats');
    }

    public function test_admin_can_update_feedback_status(): void
    {
        $admin = $this->staffWithPermission('admin.feedbacks.update-status');
        $submitter = User::factory()->create();
        $feedback = $this->makeFeedback($submitter);

        $this->actingAs($admin)
            ->put(route('admin.feedbacks.update-status', $feedback->id), [
                'status'      => 'resolved',
                'admin_notes' => 'Fixed in v1.4.',
            ])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('feedbacks', [
            'id'          => $feedback->id,
            'status'      => 'resolved',
            'admin_notes' => 'Fixed in v1.4.',
        ]);
    }

    public function test_admin_can_delete_feedback(): void
    {
        $admin = $this->staffWithPermission('admin.feedbacks.destroy');
        $submitter = User::factory()->create();
        $feedback = $this->makeFeedback($submitter);

        $this->actingAs($admin)
            ->delete(route('admin.feedbacks.destroy', $feedback->id))
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('feedbacks', ['id' => $feedback->id]);
    }

    public function test_user_without_permission_cannot_access_feedback_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.feedbacks.index'))
            ->assertForbidden();
    }
}