<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserImportPermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_grant_and_revoke_import_permission_with_history(): void
    {
        $actor = User::factory()->create();
        $actor->forceFill(['is_admin' => true])->save();
        $target = User::factory()->create();
        $target->forceFill(['is_admin' => true])->save();
        $url = '/user-management/'.$target->id.'/import-permission';
        $this->actingAs($actor)->patch($url, ['can_import_aip' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'can_import_aip' => true]);
        $this->patch($url, ['can_import_aip' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('user_access_assignment_audit_events', 1);
        $event = DB::table('user_access_assignment_audit_events')->where('user_id', $target->id)->first();
        $this->assertSame((int) $actor->id, (int) $event->actor_id);
        $this->assertFalse(json_decode($event->old_values, true)['can_import_aip']);
        $this->assertTrue(json_decode($event->new_values, true)['can_import_aip']);
        $this->get('/user-management/'.$target->id.'/history')->assertInertia(fn (Assert $page) => $page
            ->where('events.data.0.action', 'import_permission_updated')->where('events.data.0.new_values.can_import_aip', true));
        $this->actingAs($target->fresh())->get('/aip-activities/import')->assertOk();
        $this->actingAs($actor)->patch($url, ['can_import_aip' => false])->assertSessionHasNoErrors();
        $this->actingAs($target->fresh())->get('/aip-activities/import')->assertForbidden();
        $this->assertDatabaseCount('user_access_assignment_audit_events', 2);
    }

    public function test_guests_non_admins_and_ineligible_targets_are_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $regular = User::factory()->create();
        $url = '/user-management/'.$admin->id.'/import-permission';
        $this->patch($url, ['can_import_aip' => true])->assertRedirect(route('login'));
        $this->actingAs($regular)->patch($url, ['can_import_aip' => true])->assertForbidden();
        $this->actingAs($admin)->patch($url, ['can_import_aip' => true])->assertSessionHasErrors('can_import_aip');
        $this->patch('/user-management/'.$regular->id.'/import-permission', ['can_import_aip' => true])->assertSessionHasErrors('can_import_aip');
        $inactive = User::factory()->create();
        $inactive->forceFill(['is_admin' => true, 'is_active' => false])->save();
        $this->patch('/user-management/'.$inactive->id.'/import-permission', ['can_import_aip' => true])->assertSessionHasErrors('can_import_aip');
        $this->assertDatabaseCount('user_access_assignment_audit_events', 0);
    }
}
