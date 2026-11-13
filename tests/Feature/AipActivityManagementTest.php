<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Models\Subject;
use App\Models\User;
use App\Services\OfficerSubjectAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AipActivityManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_regular_users_cannot_manage_activities(): void
    {
        $this->get('/aip-activities')->assertRedirect(route('login'));
        $this->post('/aip-activities', [])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get('/aip-activities')->assertForbidden();
        $this->post('/aip-activities', [])->assertForbidden();
    }

    public function test_administrator_can_create_and_search_an_activity_with_an_audit_event(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $year = DB::table('financial_years')->insertGetId(['year' => 2025, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()]);
        $office = Office::create(['code' => 'OFF-01', 'name' => 'Example office', 'type' => 'zonal', 'is_active' => true]);
        $designation = Designation::create(['code' => 'DES-01', 'name' => 'Example designation', 'is_active' => true]);
        $officer = Officer::create(['office_id' => $office->id, 'designation_id' => $designation->id, 'name' => 'Example officer', 'is_active' => true]);
        $subject = Subject::create(['office_id' => $office->id, 'code' => 'SUB-01', 'name' => 'Example subject', 'is_active' => true]);
        app(OfficerSubjectAssignmentService::class)->assign(['officer_id' => $officer->id, 'subject_id' => $subject->id], $admin);
        $this->actingAs($admin)->post('/aip-activities', [
            'financial_year_id' => $year, 'activity_number' => '001', 'description' => 'Example activity',
            'location' => 'Provincial level', 'officer_id' => $officer->id, 'subject_id' => $subject->id,
        ])->assertRedirect(route('aip-activities.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('aip_activities', ['financial_year_id' => $year, 'activity_number' => '001', 'officer_id' => $officer->id, 'subject_id' => $subject->id]);
        $this->assertDatabaseHas('aip_activity_audit_events', ['actor_id' => $admin->id, 'action' => 'created']);
        $this->get('/aip-activities?search=001')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('aip-activities/index')->where('search', '001')->has('activities.data', 1)
            ->where('activities.data.0.activity_number', '001')->where('activities.data.0.subject.code', 'SUB-01'));
    }

    public function test_a_closed_year_cannot_receive_a_new_activity(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $year = DB::table('financial_years')->insertGetId(['year' => 2025, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'is_closed' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin)->post('/aip-activities', ['financial_year_id' => $year, 'activity_number' => '001', 'description' => 'Example'])
            ->assertSessionHasErrors('financial_year_id');
        $this->assertDatabaseCount('aip_activities', 0);
    }
}
