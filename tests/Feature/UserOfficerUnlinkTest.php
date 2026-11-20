<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserAccessAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserOfficerUnlinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_remove_link_once_with_audit_and_access_ends(): void
    {
        [$admin, $user, $office, $officer] = $this->setupLink();
        $url = '/user-management/'.$user->id.'/officer';
        $this->delete($url)->assertRedirect(route('login'));
        $this->actingAs($user)->delete($url)->assertForbidden();
        $this->get('/aip-activities')->assertOk();
        $this->actingAs($admin)->delete($url)->assertRedirect('/user-management');
        $this->delete($url)->assertRedirect('/user-management');
        $this->assertDatabaseCount('user_officer_links', 0);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 1);
        $this->assertDatabaseHas('user_access_assignment_audit_events', ['user_id' => $user->id, 'actor_id' => $admin->id, 'action' => 'officer_unlinked']);
        $this->actingAs($user)->get('/aip-activities')->assertForbidden();
    }

    public function test_changing_role_or_office_removes_link_and_changing_back_does_not_restore_it(): void
    {
        [$admin, $user, $office, $officer] = $this->setupLink();
        $service = app(UserAccessAssignmentService::class);
        $service->assign(['user_id' => $user->id, 'role' => 'subject_director', 'office_id' => $office], $admin);
        $this->assertDatabaseHas('user_officer_links', ['user_id' => $user->id, 'officer_id' => $officer]);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 0);
        $service->assign(['user_id' => $user->id, 'role' => 'zonal_admin', 'office_id' => $office], $admin);
        $this->assertDatabaseCount('user_officer_links', 0);
        $this->assertDatabaseHas('user_access_assignment_audit_events', ['user_id' => $user->id, 'action' => 'officer_unlinked']);
        $service->assign(['user_id' => $user->id, 'role' => 'subject_director', 'office_id' => $office], $admin);
        $this->actingAs($user)->get('/aip-activities')->assertForbidden();
    }

    private function setupLink(): array
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        $office = DB::table('offices')->insertGetId(['code' => 'Z1', 'name' => 'Zone 1', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $designation = DB::table('designations')->insertGetId(['code' => 'D1', 'name' => 'Director', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $officer = DB::table('officers')->insertGetId(['office_id' => $office, 'designation_id' => $designation, 'name' => 'Officer', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'subject_director', 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_officer_links')->insert(['user_id' => $user->id, 'officer_id' => $officer, 'created_at' => now(), 'updated_at' => now()]);
        return [$admin, $user, $office, $officer];
    }
}
