<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Models\Subject;
use App\Models\User;
use App\Services\AipActivityOfficeService;
use App\Services\OfficerSubjectAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AipActivityOfficeAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function activity(): AipActivity
    {
        $year = DB::table('financial_years')->insertGetId([
            'year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
            'is_closed' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return AipActivity::create([
            'financial_year_id' => $year, 'activity_number' => '123',
            'description' => 'Science seminar',
        ]);
    }

    private function pair(string $code): array
    {
        $office = Office::create([
            'code' => 'OFF-'.$code, 'name' => 'Office '.$code,
            'type' => 'zonal', 'is_active' => true,
        ]);
        $designation = Designation::create([
            'code' => 'DES-'.$code, 'name' => 'Designation '.$code, 'is_active' => true,
        ]);
        $officer = Officer::create([
            'office_id' => $office->id, 'designation_id' => $designation->id,
            'name' => 'Officer '.$code, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'office_id' => $office->id, 'code' => 'SUB-'.$code,
            'name' => 'Subject '.$code, 'is_active' => true,
        ]);

        return [$office, $officer, $subject];
    }

    private function assign(Officer $officer, Subject $subject): int
    {
        return app(OfficerSubjectAssignmentService::class)->assign([
            'officer_id' => $officer->id, 'subject_id' => $subject->id,
        ]);
    }

    private function assertRejected(array $input, string $field): void
    {
        try {
            app(AipActivityOfficeService::class)->create($input);
            $this->fail('The invalid link should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }

        $this->assertDatabaseCount('aip_activity_offices', 0);
        $this->assertDatabaseCount('aip_activity_office_audit_events', 0);
    }

    public function test_two_offices_can_have_different_assigned_pairs_and_audit_values(): void
    {
        $activity = $this->activity();
        [$office1, $officer1, $subject1] = $this->pair('01');
        [$office2, $officer2, $subject2] = $this->pair('02');
        $this->assign($officer1, $subject1);
        $this->assign($officer2, $subject2);
        $actor = User::factory()->create();
        $service = app(AipActivityOfficeService::class);

        foreach ([[$office1, $officer1, $subject1], [$office2, $officer2, $subject2]] as [$office, $officer, $subject]) {
            $id = $service->create([
                'aip_activity_id' => $activity->id, 'office_id' => $office->id,
                'officer_id' => $officer->id, 'subject_id' => $subject->id,
            ], $actor);

            $this->assertDatabaseHas('aip_activity_offices', [
                'id' => $id, 'aip_activity_id' => $activity->id, 'office_id' => $office->id,
                'officer_id' => $officer->id, 'subject_id' => $subject->id,
            ]);
            $event = DB::table('aip_activity_office_audit_events')
                ->where('aip_activity_office_id', $id)->first();
            $this->assertNotNull($event);
            $this->assertEquals($actor->id, $event->actor_id);
            $this->assertSame('created', $event->action);
            $values = json_decode($event->new_values, true, 512, JSON_THROW_ON_ERROR);
            $this->assertEquals($office->id, $values['office_id']);
            $this->assertEquals($officer->id, $values['officer_id']);
            $this->assertEquals($subject->id, $values['subject_id']);
        }
        $this->assertDatabaseCount('aip_activity_offices', 2);
        $this->assertDatabaseCount('aip_activity_office_audit_events', 2);
    }

    public function test_subject_and_officer_must_be_given_together(): void
    {
        $activity = $this->activity();
        [$office, $officer, $subject] = $this->pair('01');
        $base = ['aip_activity_id' => $activity->id, 'office_id' => $office->id];
        $this->assertRejected($base + ['subject_id' => $subject->id], 'officer_id');
        $this->assertRejected($base + ['officer_id' => $officer->id], 'subject_id');
    }

    public function test_a_pair_from_another_office_is_rejected(): void
    {
        $activity = $this->activity();
        [$selected] = $this->pair('01');
        [, $officer, $subject] = $this->pair('02');
        $this->assign($officer, $subject);
        $this->assertRejected([
            'aip_activity_id' => $activity->id, 'office_id' => $selected->id,
            'officer_id' => $officer->id, 'subject_id' => $subject->id,
        ], 'officer_id');
    }

    public function test_a_subject_from_another_office_is_rejected(): void
    {
        $activity = $this->activity();
        [$office, $officer] = $this->pair('01');
        [, , $otherSubject] = $this->pair('02');
        $this->assertRejected([
            'aip_activity_id' => $activity->id, 'office_id' => $office->id,
            'officer_id' => $officer->id, 'subject_id' => $otherSubject->id,
        ], 'subject_id');
    }

    public function test_an_unassigned_or_ended_pair_is_rejected(): void
    {
        $activity = $this->activity();
        [$office, $officer, $subject] = $this->pair('01');
        $input = [
            'aip_activity_id' => $activity->id, 'office_id' => $office->id,
            'officer_id' => $officer->id, 'subject_id' => $subject->id,
        ];
        $this->assertRejected($input, 'subject_id');
        $assignment = $this->assign($officer, $subject);
        app(OfficerSubjectAssignmentService::class)->end($assignment);
        $this->assertRejected($input, 'subject_id');
    }

    public function test_missing_officer_and_subject_are_rejected(): void
    {
        $activity = $this->activity();
        [$office, $officer, $subject] = $this->pair('01');
        $base = ['aip_activity_id' => $activity->id, 'office_id' => $office->id];
        $this->assertRejected($base + ['officer_id' => 999999, 'subject_id' => $subject->id], 'officer_id');
        $this->assertRejected($base + ['officer_id' => $officer->id, 'subject_id' => 999999], 'subject_id');
    }
}
