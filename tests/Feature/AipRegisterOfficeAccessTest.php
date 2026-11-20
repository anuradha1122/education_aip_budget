<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AipRegisterOfficeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_viewer_only_receives_own_office_activities_and_assignments(): void
    {
        $year = DB::table('financial_years')->insertGetId(['year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['A', 'B'] as $code) {
            $offices[$code] = DB::table('offices')->insertGetId(['code' => $code, 'name' => 'Office '.$code, 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (['10', '20', '30'] as $number) {
            $activities[$number] = DB::table('aip_activities')->insertGetId(['financial_year_id' => $year, 'activity_number' => $number, 'description' => 'Activity '.$number, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ([['10', 'A'], ['10', 'B'], ['20', 'B'], ['30', 'A']] as [$number, $office]) {
            DB::table('aip_activity_offices')->insert(['aip_activity_id' => $activities[$number], 'office_id' => $offices[$office], 'created_at' => now(), 'updated_at' => now()]);
        }
        $viewer = User::factory()->create();
        DB::table('user_access_assignments')->insert(['user_id' => $viewer->id, 'role' => 'zonal_admin', 'office_id' => $offices['A'], 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($viewer)->get('/aip-activities?search=10')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('aip-activities/index')->where('canManageAip', false)->where('canImportAip', false)
            ->has('activities.data', 1)->has('activities.data.0.office_assignments', 1)
            ->where('activities.data.0.office_assignments.0.office_name', 'Office A')
            ->where('activities.data.0.subject', null)->where('activities.data.0.officer', null)
            ->missing('activities.data.0.subject_id')->missing('activities.data.0.officer_id')
            ->has('officers', 0)->has('assignments', 0));
        $this->get('/aip-activities?search=20')->assertOk()->assertInertia(fn (Assert $page) => $page->has('activities.data', 0));
        $this->get('/aip-activities/audit')->assertForbidden();
        $this->post('/aip-activities', [])->assertForbidden();
        $this->get('/aip-activities/import')->assertForbidden();
        $this->get('/aip-activity-offices')->assertForbidden();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/aip-activities?search=10')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canManageAip', true)->has('activities.data.0.office_assignments', 2));
    }

    public function test_permission_and_active_office_are_required(): void
    {
        $this->get('/aip-activities')->assertRedirect(route('login'));
        $office = DB::table('offices')->insertGetId(['code' => 'A', 'name' => 'Office A', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/aip-activities')->assertForbidden();
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'subject_director', 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        $this->get('/aip-activities')->assertForbidden();
        DB::table('user_access_assignments')->where('user_id', $user->id)->update(['role' => 'zonal_admin']);
        $this->get('/aip-activities')->assertOk();
        DB::table('offices')->where('id', $office)->update(['is_active' => false]);
        $this->get('/aip-activities')->assertForbidden();
        DB::table('user_access_assignments')->where('user_id', $user->id)->update(['office_id' => null]);
        $this->get('/aip-activities')->assertForbidden();
    }
}
