<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AipActivityRegisterOfficeDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_shows_office_assignments_and_common_funding(): void
    {
        $year = DB::table('financial_years')->insertGetId([
            'year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
            'is_closed' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $activity = DB::table('aip_activities')->insertGetId([
            'financial_year_id' => $year, 'activity_number' => '452', 'description' => 'Science seminar',
            'level' => 'Zonal', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $designation = DB::table('designations')->insertGetId([
            'code' => 'DES', 'name' => 'Director', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['01', '02'] as $code) {
            $office = DB::table('offices')->insertGetId([
                'code' => 'OFF-'.$code, 'name' => 'Office '.$code, 'type' => 'zonal',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $subject = DB::table('subjects')->insertGetId([
                'office_id' => $office, 'code' => 'SCI-'.$code, 'name' => 'Science',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $officer = DB::table('officers')->insertGetId([
                'office_id' => $office, 'designation_id' => $designation, 'name' => 'Officer '.$code,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('aip_activity_offices')->insert([
                'aip_activity_id' => $activity, 'office_id' => $office,
                'subject_id' => $subject, 'officer_id' => $officer,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $fund = DB::table('funds')->insertGetId([
            'code' => 'PF', 'name' => 'Provincial fund', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $vote = DB::table('votes')->insertGetId([
            'code' => 'V1', 'description' => 'Example vote', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('aip_activity_funding_entries')->insert([
            'aip_activity_id' => $activity, 'fund_id' => $fund, 'vote_id' => $vote,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->get('/aip-activities?search=452')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('aip-activities/index')
                ->has('activities.data', 1)
                ->where('activities.data.0.activity_number', '452')
                ->where('activities.data.0.subject', null)
                ->where('activities.data.0.officer', null)
                ->has('activities.data.0.office_assignments', 2)
                ->where('activities.data.0.office_assignments.0.office_name', 'Office 01')
                ->where('activities.data.0.office_assignments.0.subject_name', 'Science')
                ->where('activities.data.0.office_assignments.0.officer_name', 'Officer 01')
                ->where('activities.data.0.office_assignments.1.office_name', 'Office 02')
                ->where('activities.data.0.office_assignments.1.officer_name', 'Officer 02')
                ->where('activities.data.0.funding.fund_code', 'PF')
                ->where('activities.data.0.funding.vote_code', 'V1'));
    }

    public function test_register_handles_activity_without_office_or_funding(): void
    {
        $year = DB::table('financial_years')->insertGetId([
            'year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
            'is_closed' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('aip_activities')->insert([
            'financial_year_id' => $year, 'activity_number' => '453', 'description' => 'Example activity',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/aip-activities?search=453')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('activities.data', 1)
                ->has('activities.data.0.office_assignments', 0)
                ->where('activities.data.0.funding', null));
    }
}
