<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProvincialAipRegisterAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_provincial_user_sees_all_offices_can_create_but_cannot_manage_or_import(): void
    {
        $year = DB::table('financial_years')->insertGetId(['year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['A', 'B'] as $code) {
            $office = DB::table('offices')->insertGetId(['code' => $code, 'name' => 'Office '.$code, 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $activity = DB::table('aip_activities')->insertGetId(['financial_year_id' => $year, 'activity_number' => $code, 'description' => 'Activity '.$code, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('aip_activity_offices')->insert(['aip_activity_id' => $activity, 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        }
        $user = User::factory()->create();
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'provincial_admin', 'office_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->get('/aip-activities')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 2)->where('canManageAip', false)->where('canCreateAip', true)->where('canImportAip', false)
            ->has('officers', 0)->has('assignments', 0)->where('activities.data.0.subject', null)->missing('activities.data.0.subject_id')
            ->where('permissions.view_aip_register', true));
        $this->get('/aip-activities?search=Activity%20A')->assertInertia(fn (Assert $page) => $page->has('activities.data', 1)->where('activities.data.0.office_assignments.0.office_name', 'Office A'));
        $this->get('/aip-activities/import')->assertForbidden();
        $this->post('/aip-activities', [])->assertSessionHasErrors('financial_year_id');
        $this->get('/user-management')->assertForbidden();
        DB::table('user_access_assignments')->where('user_id', $user->id)->delete();
        $this->get('/aip-activities')->assertForbidden();
    }

    public function test_provincial_role_with_office_is_not_treated_as_valid_province_scope(): void
    {
        $user = User::factory()->create();
        $office = DB::table('offices')->insertGetId(['code' => 'Z', 'name' => 'Office Z', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'provincial_admin', 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->get('/aip-activities')->assertForbidden();
    }
}
