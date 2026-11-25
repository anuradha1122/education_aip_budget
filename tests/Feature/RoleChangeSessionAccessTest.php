<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserAccessAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleChangeSessionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_change_removes_provincial_permissions_from_an_existing_session(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        $office = DB::table('offices')->insertGetId([
            'code' => 'ROLE-TEST', 'name' => 'Role test office', 'type' => 'zonal',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = app(UserAccessAssignmentService::class);
        $service->assign(['user_id' => $user->id, 'role' => 'provincial_admin', 'office_id' => null], $admin);

        $this->actingAs($user)->get('/financial-years')->assertOk();
        $this->get('/offices')->assertOk();
        $this->get('/aip-activity-offices')->assertOk();
        $this->get('/aip-funding-entries')->assertOk();

        // Change the role as an administrator while the user's session remains active.
        $service->assign(['user_id' => $user->id, 'role' => 'zonal_admin', 'office_id' => $office], $admin);

        $this->get('/dashboard')->assertOk();
        $this->get('/financial-years')->assertForbidden();
        $this->post('/financial-years', [])->assertForbidden();
        $this->get('/offices')->assertForbidden();
        $this->post('/offices', [])->assertForbidden();
        $this->get('/aip-activity-offices')->assertForbidden();
        $this->post('/aip-activity-offices', [])->assertForbidden();
        $this->get('/aip-funding-entries')->assertForbidden();
        $this->post('/aip-funding-entries', [])->assertForbidden();
        $this->get('/aip-activities')->assertOk();
        $this->assertDatabaseHas('user_access_assignment_audit_events', [
            'user_id' => $user->id, 'actor_id' => $admin->id, 'action' => 'updated',
        ]);
    }
}
