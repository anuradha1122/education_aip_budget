<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Models\Subject;
use App\Models\User;
use App\Services\OfficerSubjectAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfficerSubjectAssignmentAuditTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $office = Office::create(['code' => 'OFF-01', 'name' => 'Example office', 'type' => 'zonal', 'is_active' => true]);
        $other = Office::create(['code' => 'OFF-02', 'name' => 'Other office', 'type' => 'zonal', 'is_active' => true]);
        $designation = Designation::create(['code' => 'DES-01', 'name' => 'Example designation', 'is_active' => true]);
        $officer = Officer::create(['office_id' => $office->id, 'designation_id' => $designation->id, 'name' => 'Example officer', 'is_active' => true]);
        $subject = Subject::create(['office_id' => $office->id, 'code' => 'SUB-01', 'name' => 'Example subject', 'is_active' => true]);
        $otherSubject = Subject::create(['office_id' => $other->id, 'code' => 'SUB-02', 'name' => 'Other subject', 'is_active' => true]);
        return [$office, $officer, $subject, $otherSubject];
    }

    public function test_assignment_records_the_actor_and_the_office_at_assignment(): void
    {
        [$office, $officer, $subject] = $this->fixture();
        $actor = User::factory()->create();
        $id = app(OfficerSubjectAssignmentService::class)->assign([
            'officer_id' => $officer->id, 'subject_id' => $subject->id,
        ], $actor);
        $this->assertDatabaseHas('officer_subject_assignment_audit_events', [
            'assignment_id' => $id, 'actor_id' => $actor->id, 'action' => 'assigned',
            'officer_id' => $officer->id, 'subject_id' => $subject->id, 'office_id' => $office->id,
        ]);
    }

    public function test_rejected_assignment_has_no_audit_event(): void
    {
        [, $officer, , $otherSubject] = $this->fixture();
        try {
            app(OfficerSubjectAssignmentService::class)->assign([
                'officer_id' => $officer->id, 'subject_id' => $otherSubject->id,
            ], User::factory()->create());
            $this->fail('The other office should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subject_id', $exception->errors());
        }
        $this->assertDatabaseCount('officer_subject_assignments', 0);
        $this->assertDatabaseCount('officer_subject_assignment_audit_events', 0);
    }
}
