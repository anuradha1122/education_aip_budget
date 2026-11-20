<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserAccessAssignmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserAccessAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_assign_and_update_with_audit_without_granting_permissions(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        $office = DB::table('offices')->insertGetId(['code' => 'Z1', 'name' => 'Zone 1', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(UserAccessAssignmentService::class);
        $input = ['user_id' => $user->id, 'role' => 'zonal_admin', 'office_id' => $office];
        $service->assign($input, $admin);
        $service->assign($input, $admin);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 1);
        $this->assertDatabaseHas('user_access_assignments', $input);
        $this->assertFalse($user->fresh()->can('manage-master-data'));
        $service->assign(['user_id' => $user->id, 'role' => 'subject_director', 'office_id' => $office], $admin);
        $this->assertDatabaseCount('user_access_assignments', 1);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 2);
        $event = DB::table('user_access_assignment_audit_events')->where('action', 'updated')->first();
        $this->assertSame('zonal_admin', json_decode($event->old_values, true)['role']);
        $this->assertSame('subject_director', json_decode($event->new_values, true)['role']);
        $this->assertSame((int) $admin->id, (int) $event->actor_id);
    }

    public function test_non_admin_cannot_assign_access(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();
        try {
            app(UserAccessAssignmentService::class)->assign(['user_id' => $user->id, 'role' => 'provincial_admin'], $actor);
            $this->fail('Access should be denied.');
        } catch (AuthorizationException $exception) {
            $this->assertDatabaseCount('user_access_assignments', 0);
            $this->assertDatabaseCount('user_access_assignment_audit_events', 0);
        }
    }

    public function test_invalid_roles_and_office_scopes_are_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        $inactive = DB::table('offices')->insertGetId(['code' => 'Z1', 'name' => 'Zone 1', 'type' => 'zonal', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()]);
        $service = app(UserAccessAssignmentService::class);
        foreach ([
            ['user_id' => $user->id, 'role' => 'zonal_admin'],
            ['user_id' => $user->id, 'role' => 'subject_director', 'office_id' => $inactive],
            ['user_id' => $user->id, 'role' => 'provincial_admin', 'office_id' => $inactive],
            ['user_id' => $user->id, 'role' => 'unknown'],
            ['user_id' => 999999, 'role' => 'provincial_admin'],
        ] as $input) {
            try {
                $service->assign($input, $admin);
                $this->fail('Invalid assignment should be rejected.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertDatabaseCount('user_access_assignments', 0);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 0);
    }
}
