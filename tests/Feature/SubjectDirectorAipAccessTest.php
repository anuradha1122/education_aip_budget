<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubjectDirectorAipAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_sees_only_their_own_office_activity_links(): void
    {
        $year = DB::table('financial_years')->insertGetId(['year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()]);
        $office = $this->office('A');
        $otherOffice = $this->office('B');
        $first = $this->officer($office, 'Officer One');
        $second = $this->officer($office, 'Officer Two');
        $outside = $this->officer($otherOffice, 'Other Office');
        $user = User::factory()->create();
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'subject_director', 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_officer_links')->insert(['user_id' => $user->id, 'officer_id' => $first, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['101', $office, $first], ['102', $office, $second], ['103', $otherOffice, $outside]] as [$number, $officeId, $officerId]) {
            $activity = DB::table('aip_activities')->insertGetId(['financial_year_id' => $year, 'activity_number' => $number, 'description' => 'Activity '.$number, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('aip_activity_offices')->insert(['aip_activity_id' => $activity, 'office_id' => $officeId, 'officer_id' => $officerId, 'created_at' => now(), 'updated_at' => now()]);
            if ($number === '101') DB::table('aip_activity_offices')->insert(['aip_activity_id' => $activity, 'office_id' => $otherOffice, 'officer_id' => $outside, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actingAs($user)->get('/aip-activities')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 1)->where('activities.data.0.activity_number', '101')
            ->has('activities.data.0.office_assignments', 1)->where('activities.data.0.office_assignments.0.officer_name', 'Officer One')
            ->where('activities.data.0.subject', null)->where('activities.data.0.officer', null)
            ->missing('activities.data.0.officer_id')->where('canManageAip', false)->where('canImportAip', false));
        $this->get('/aip-activities?search=102')->assertOk()->assertInertia(fn (Assert $page) => $page->has('activities.data', 0));
        $this->get('/aip-activities?search=103')->assertOk()->assertInertia(fn (Assert $page) => $page->has('activities.data', 0));
        $this->post('/aip-activities', [])->assertForbidden();
        $this->get('/aip-activities/import')->assertForbidden();
        $this->get('/aip-activities/audit')->assertForbidden();
    }

    public function test_scope_is_denied_when_identity_or_office_becomes_invalid(): void
    {
        $office = $this->office('A');
        $otherOffice = $this->office('B');
        $officer = $this->officer($office, 'Director');
        $user = User::factory()->create();
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'subject_director', 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->get('/aip-activities')->assertForbidden();
        DB::table('user_officer_links')->insert(['user_id' => $user->id, 'officer_id' => $officer, 'created_at' => now(), 'updated_at' => now()]);
        $this->get('/aip-activities')->assertOk();
        DB::table('officers')->where('id', $officer)->update(['is_active' => false]);
        $this->get('/aip-activities')->assertForbidden();
        DB::table('officers')->where('id', $officer)->update(['is_active' => true, 'office_id' => $otherOffice]);
        $this->get('/aip-activities')->assertForbidden();
        DB::table('officers')->where('id', $officer)->update(['office_id' => $office]);
        DB::table('offices')->where('id', $office)->update(['is_active' => false]);
        $this->get('/aip-activities')->assertForbidden();
    }

    private function office(string $code): int
    {
        return DB::table('offices')->insertGetId(['code' => $code, 'name' => 'Office '.$code, 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function officer(int $office, string $name): int
    {
        $designation = DB::table('designations')->insertGetId(['code' => 'D'.uniqid(), 'name' => 'Director', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('officers')->insertGetId(['office_id' => $office, 'designation_id' => $designation, 'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}
