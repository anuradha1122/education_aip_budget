<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProvincialAipCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_provincial_administrator_can_create_audited_activity_but_not_manage_master_data(): void
    {
        $user = User::factory()->create();
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'provincial_admin', 'office_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        $year = DB::table('financial_years')->insertGetId(['year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->get('/aip-activities')->assertInertia(fn (Assert $page) => $page->where('canCreateAip', true)->where('canManageAip', false)->where('canImportAip', false));
        $this->post('/aip-activities', ['financial_year_id' => $year, 'activity_number' => '001', 'description' => 'Provincial activity'])->assertSessionHasNoErrors()->assertRedirect(route('aip-activities.index'));
        $activity = DB::table('aip_activities')->where('activity_number', '001')->first();
        $this->assertNotNull($activity);
        $this->assertDatabaseHas('aip_activity_audit_events', ['aip_activity_id' => $activity->id, 'actor_id' => $user->id, 'action' => 'created']);
        $this->get('/user-management')->assertOk();
        $this->get('/aip-activities/import')->assertForbidden();
        DB::table('financial_years')->where('id', $year)->update(['is_closed' => true]);
        $this->post('/aip-activities', ['financial_year_id' => $year, 'activity_number' => '002', 'description' => 'Closed'])->assertSessionHasErrors('financial_year_id');
        $this->assertDatabaseMissing('aip_activities', ['activity_number' => '002']);
    }

    public function test_guests_and_zonal_users_cannot_create_activities(): void
    {
        $this->post('/aip-activities', [])->assertRedirect(route('login'));
        $user = User::factory()->create();
        $office = DB::table('offices')->insertGetId(['code' => 'Z', 'name' => 'Zone', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'zonal_admin', 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->post('/aip-activities', [])->assertForbidden();
    }
}
