<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Models\Subject;
use App\Services\OfficerSubjectAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfficerSubjectAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $office = Office::create(['code' => 'OFF-01', 'name' => 'Office one', 'type' => 'zonal', 'is_active' => true]);
        $otherOffice = Office::create(['code' => 'OFF-02', 'name' => 'Office two', 'type' => 'zonal', 'is_active' => true]);
        $designation = Designation::create(['code' => 'DES-01', 'name' => 'Example designation', 'is_active' => true]);
        $officer = Officer::create(['office_id' => $office->id, 'designation_id' => $designation->id, 'name' => 'Example officer', 'is_active' => true]);
        $first = Subject::create(['office_id' => $office->id, 'code' => 'SUB-01', 'name' => 'Subject one', 'is_active' => true]);
        $second = Subject::create(['office_id' => $office->id, 'code' => 'SUB-02', 'name' => 'Subject two', 'is_active' => true]);
        $other = Subject::create(['office_id' => $otherOffice->id, 'code' => 'SUB-03', 'name' => 'Other office subject', 'is_active' => true]);
        return [$officer, $first, $second, $other];
    }

    public function test_an_officer_can_be_assigned_to_multiple_subjects_in_the_same_office(): void
    {
        [$officer, $first, $second] = $this->fixtures();
        $service = app(OfficerSubjectAssignmentService::class);
        $service->assign(['officer_id' => $officer->id, 'subject_id' => $first->id]);
        $service->assign(['officer_id' => $officer->id, 'subject_id' => $second->id]);
        $this->assertDatabaseCount('officer_subject_assignments', 2);
        $this->assertDatabaseHas('officer_subject_assignments', ['officer_id' => $officer->id, 'subject_id' => $second->id]);
    }

    public function test_it_rejects_subjects_from_another_office(): void
    {
        [$officer, , , $other] = $this->fixtures();
        try {
            app(OfficerSubjectAssignmentService::class)->assign(['officer_id' => $officer->id, 'subject_id' => $other->id]);
            $this->fail('A different office should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subject_id', $exception->errors());
        }
        $this->assertDatabaseCount('officer_subject_assignments', 0);
    }

    public function test_it_rejects_a_duplicate_assignment(): void
    {
        [$officer, $subject] = $this->fixtures();
        $service = app(OfficerSubjectAssignmentService::class);
        $service->assign(['officer_id' => $officer->id, 'subject_id' => $subject->id]);
        try {
            $service->assign(['officer_id' => $officer->id, 'subject_id' => $subject->id]);
            $this->fail('A duplicate assignment should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subject_id', $exception->errors());
        }
        $this->assertDatabaseCount('officer_subject_assignments', 1);
    }

    public function test_it_rejects_missing_officers_and_subjects(): void
    {
        [$officer, $subject] = $this->fixtures();
        $service = app(OfficerSubjectAssignmentService::class);
        foreach ([['officer_id' => 999999, 'subject_id' => $subject->id], ['officer_id' => $officer->id, 'subject_id' => 999999]] as $input) {
            try {
                $service->assign($input);
                $this->fail('A missing reference should be rejected.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertDatabaseCount('officer_subject_assignments', 0);
    }

    public function test_a_subject_can_have_multiple_officers_in_the_same_office(): void
    {
        [$firstOfficer, $subject] = $this->fixtures();

        $secondOfficer = Officer::create([
            'office_id' => $firstOfficer->office_id,
            'designation_id' => $firstOfficer->designation_id,
            'name' => 'Second example officer',
            'is_active' => true,
        ]);

        $service = app(OfficerSubjectAssignmentService::class);

        $service->assign([
            'officer_id' => $firstOfficer->id,
            'subject_id' => $subject->id,
        ]);

        $service->assign([
            'officer_id' => $secondOfficer->id,
            'subject_id' => $subject->id,
        ]);

        $this->assertDatabaseCount('officer_subject_assignments', 2);

        $this->assertDatabaseHas('officer_subject_assignments', [
            'officer_id' => $secondOfficer->id,
            'subject_id' => $subject->id,
        ]);
    }
}
