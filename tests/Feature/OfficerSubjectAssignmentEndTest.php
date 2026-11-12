<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Models\Subject;
use App\Models\User;
use App\Services\AipActivityService;
use App\Services\OfficerSubjectAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfficerSubjectAssignmentEndTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        $office = Office::create(['code' => 'OFF-01', 'name' => 'Example office', 'type' => 'zonal', 'is_active' => true]);
        $designation = Designation::create(['code' => 'DES-01', 'name' => 'Example designation', 'is_active' => true]);
        $officer = Officer::create(['office_id' => $office->id, 'designation_id' => $designation->id, 'name' => 'Example officer', 'is_active' => true]);
        $subject = Subject::create(['office_id' => $office->id, 'code' => 'SUB-01', 'name' => 'Example subject', 'is_active' => true]);
        $year = DB::table('financial_years')->insertGetId(['year' => 2025, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()]);
        return [$office, $officer, $subject, $year];
    }

    public function test_end_preserves_history_and_a_pair_can_be_reassigned(): void
    {
        [$office, $officer, $subject, $year] = $this->data();
        $actor = User::factory()->create();
        $service = app(OfficerSubjectAssignmentService::class);
        $input = ['officer_id' => $officer->id, 'subject_id' => $subject->id];
        $old = $service->assign($input, $actor);
        $activity = app(AipActivityService::class)->create(['financial_year_id' => $year, 'activity_number' => '001', 'description' => 'Example', ...$input], $actor);
        $service->end($old, $actor);
        $record = DB::table('officer_subject_assignments')->where('id', $old)->first();
        $this->assertNotNull($record->ended_at);
        $this->assertNull($record->active_marker);
        $this->assertDatabaseHas('officer_subject_assignment_audit_events', ['assignment_id' => $old, 'actor_id' => $actor->id, 'office_id' => $office->id, 'action' => 'ended']);
        $this->assertDatabaseHas('aip_activities', ['id' => $activity->id, 'subject_id' => $subject->id, 'officer_id' => $officer->id]);
        $new = $service->assign($input, $actor);
        $this->assertNotSame($old, $new);
        $this->assertDatabaseCount('officer_subject_assignments', 2);
        $this->assertDatabaseHas('officer_subject_assignments', ['id' => $new, 'active_marker' => 1, 'ended_at' => null]);
    }

    public function test_an_ended_assignment_is_not_valid_for_a_new_activity(): void
    {
        [, $officer, $subject, $year] = $this->data();
        $service = app(OfficerSubjectAssignmentService::class);
        $input = ['officer_id' => $officer->id, 'subject_id' => $subject->id];
        $service->end($service->assign($input));
        try {
            app(AipActivityService::class)->create(['financial_year_id' => $year, 'activity_number' => '001', 'description' => 'Example', ...$input]);
            $this->fail('An ended assignment should not be accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subject_id', $exception->errors());
        }
        $this->assertDatabaseCount('aip_activities', 0);
    }

    public function test_an_assignment_cannot_be_ended_twice(): void
    {
        [, $officer, $subject] = $this->data();
        $service = app(OfficerSubjectAssignmentService::class);
        $id = $service->assign(['officer_id' => $officer->id, 'subject_id' => $subject->id]);
        $service->end($id);
        try {
            $service->end($id);
            $this->fail('An ended assignment cannot be ended twice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assignment', $exception->errors());
        }
        $this->assertSame(1, DB::table('officer_subject_assignment_audit_events')->where('assignment_id', $id)->where('action', 'ended')->count());
    }

    public function test_guests_and_regular_users_cannot_end_assignments(): void
    {
        $this->patch('/officer-subject-assignments/1/end')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->patch('/officer-subject-assignments/1/end')->assertForbidden();
    }

    public function test_admin_can_end_an_assignment(): void
    {
        [, $officer, $subject] = $this->data();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $id = app(OfficerSubjectAssignmentService::class)->assign(['officer_id' => $officer->id, 'subject_id' => $subject->id], $admin);
        $this->actingAs($admin)->patch('/officer-subject-assignments/'.$id.'/end')
            ->assertRedirect(route('officer-subject-assignments.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('officer_subject_assignment_audit_events', ['assignment_id' => $id, 'actor_id' => $admin->id, 'action' => 'ended']);
    }
}
