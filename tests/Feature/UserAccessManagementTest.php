<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserAccessAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_an_assignment_and_record_actor_and_old_and_new_values(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create(['name' => 'Zonal Viewer']);
        $admin->forceFill(['name' => 'Administrator'])->save();
        $a = $this->office('A');
        $b = $this->office('B');
        app(UserAccessAssignmentService::class)->assign(['user_id' => $user->id, 'role' => 'zonal_admin', 'office_id' => $a], $admin);
        $this->actingAs($admin)->put('/user-management/'.$user->id.'/access', ['role' => 'zonal_admin', 'office_id' => $b])
            ->assertRedirect('/user-management')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_access_assignments', ['user_id' => $user->id, 'role' => 'zonal_admin', 'office_id' => $b]);
        $event = DB::table('user_access_assignment_audit_events')->where('user_id', $user->id)->where('action', 'updated')->first();
        $this->assertSame((int) $admin->id, (int) $event->actor_id);
        $this->assertSame($a, json_decode($event->old_values, true)['office_id']);
        $this->assertSame($b, json_decode($event->new_values, true)['office_id']);
        $this->get('/user-management')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('users.data.1.office_id', $b));
    }

    public function test_unauthorized_users_cannot_change_access(): void
    {
        $user = User::factory()->create();
        $this->put('/user-management/'.$user->id.'/access', [])->assertRedirect(route('login'));
        $this->actingAs($user)->put('/user-management/'.$user->id.'/access', ['role' => 'provincial_admin'])->assertForbidden();
        $this->assertDatabaseCount('user_access_assignments', 0);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 0);
    }

    public function test_invalid_office_and_existing_admin_are_rejected_without_changes(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        $office = $this->office('A', false);
        $this->actingAs($admin)->put('/user-management/'.$user->id.'/access', ['role' => 'zonal_admin', 'office_id' => $office])->assertSessionHasErrors('office_id');
        $this->put('/user-management/'.$admin->id.'/access', ['role' => 'subject_director', 'office_id' => $office])->assertSessionHasErrors('role');
        $this->assertDatabaseCount('user_access_assignments', 0);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 0);
        $this->assertTrue((bool) $admin->fresh()->is_admin);
    }

    private function office(string $code, bool $active = true): int
    {
        return DB::table('offices')->insertGetId(['code' => $code, 'name' => 'Office '.$code, 'type' => 'zonal', 'is_active' => $active, 'created_at' => now(), 'updated_at' => now()]);
    }
}
