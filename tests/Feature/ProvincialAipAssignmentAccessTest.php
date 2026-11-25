<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\Fund;
use App\Models\Office;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProvincialAipAssignmentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_provincial_admin_can_add_audited_office_and_funding_assignments_but_not_import(): void
    {
        $user = User::factory()->create();
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'provincial_admin', 'office_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        $year = DB::table('financial_years')->insertGetId(['year' => 2030, 'start_date' => '2030-01-01', 'end_date' => '2030-12-31', 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()]);
        $activity = AipActivity::create(['financial_year_id' => $year, 'activity_number' => '123', 'description' => 'Test activity']);
        $office = Office::create(['code' => 'ZONE-01', 'name' => 'Example office', 'type' => 'zonal', 'is_active' => true]);
        $vote = Vote::create(['code' => 'VOTE-01', 'description' => 'Example vote', 'is_active' => true]);
        $fund = Fund::create(['code' => 'PF', 'name' => 'Example fund', 'is_active' => true]);

        $this->actingAs($user)->get('/aip-activities')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canManageAipAssignments', true)->where('canManageAip', false)->where('canImportAip', false));
        $this->get('/aip-activity-offices')->assertOk();
        $this->get('/aip-funding-entries')->assertOk();
        $this->post('/aip-activity-offices', ['aip_activity_id' => $activity->id, 'office_id' => $office->id])->assertRedirect(route('aip-activity-offices.index'))->assertSessionHasNoErrors();
        $this->post('/aip-funding-entries', ['aip_activity_id' => $activity->id, 'vote_id' => $vote->id, 'fund_id' => $fund->id])->assertRedirect(route('aip-funding-entries.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('aip_activity_office_audit_events', ['actor_id' => $user->id, 'action' => 'created']);
        $this->assertDatabaseHas('aip_funding_entry_audit_events', ['actor_id' => $user->id, 'action' => 'created']);
        $this->get('/aip-activities/import')->assertForbidden();
        $this->get('/aip-activities/audit')->assertForbidden();

        DB::table('financial_years')->where('id', $year)->update(['is_closed' => true]);
        $other = AipActivity::create(['financial_year_id' => $year, 'activity_number' => '456', 'description' => 'Closed year activity']);
        $this->post('/aip-activity-offices', ['aip_activity_id' => $other->id, 'office_id' => $office->id])->assertSessionHasErrors('aip_activity_id');
        $this->post('/aip-funding-entries', ['aip_activity_id' => $other->id, 'vote_id' => $vote->id, 'fund_id' => $fund->id])->assertSessionHasErrors('aip_activity_id');
        $this->assertDatabaseMissing('aip_activity_offices', ['aip_activity_id' => $other->id]);
        $this->assertDatabaseMissing('aip_activity_funding_entries', ['aip_activity_id' => $other->id]);
    }

    public function test_other_roles_are_denied_and_system_admin_retains_access(): void
    {
        $this->get('/aip-activity-offices')->assertRedirect(route('login'));
        $this->post('/aip-funding-entries', [])->assertRedirect(route('login'));
        $office = Office::create(['code' => 'ZONE-01', 'name' => 'Example office', 'type' => 'zonal', 'is_active' => true]);
        foreach (['zonal_admin', 'subject_director', 'provincial_admin'] as $role) {
            $user = User::factory()->create();
            DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => $role, 'office_id' => $office->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->actingAs($user);
            $this->get('/aip-activity-offices')->assertForbidden();
            $this->post('/aip-activity-offices', [])->assertForbidden();
            $this->get('/aip-funding-entries')->assertForbidden();
            $this->post('/aip-funding-entries', [])->assertForbidden();
        }
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/aip-activity-offices')->assertOk();
        $this->get('/aip-funding-entries')->assertOk();
        $this->get('/aip-activities')->assertInertia(fn (Assert $page) => $page->where('canManageAipAssignments', true));
    }
}
