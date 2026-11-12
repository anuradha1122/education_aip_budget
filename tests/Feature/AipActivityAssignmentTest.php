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

class AipActivityAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        $office = Office::create(['code' => 'OFF-01', 'name' => 'Office one', 'type' => 'zonal', 'is_active' => true]);
        $otherOffice = Office::create(['code' => 'OFF-02', 'name' => 'Office two', 'type' => 'zonal', 'is_active' => true]);
        $designation = Designation::create(['code' => 'DES-01', 'name' => 'Designation', 'is_active' => true]);
        $officer = Officer::create(['office_id' => $office->id, 'designation_id' => $designation->id, 'name' => 'Officer one', 'is_active' => true]);
        $subject = Subject::create(['office_id' => $office->id, 'code' => 'SUB-01', 'name' => 'Subject one', 'is_active' => true]);
        $otherSubject = Subject::create(['office_id' => $otherOffice->id, 'code' => 'SUB-02', 'name' => 'Subject two', 'is_active' => true]);
        $year = DB::table('financial_years')->insertGetId([
            'year' => 2025, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31',
            'is_closed' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$year, $officer, $subject, $otherSubject];
    }

    public function test_it_saves_an_assigned_pair_and_records_it_in_the_audit(): void
    {
        [$year, $officer, $subject] = $this->data();
        app(OfficerSubjectAssignmentService::class)->assign(['officer_id' => $officer->id, 'subject_id' => $subject->id]);
        $actor = User::factory()->create();
        $activity = app(AipActivityService::class)->create([
            'financial_year_id' => $year, 'activity_number' => '001', 'description' => 'Example activity',
            'subject_id' => $subject->id, 'officer_id' => $officer->id,
        ], $actor);
        $this->assertDatabaseHas('aip_activities', ['id' => $activity->id, 'subject_id' => $subject->id, 'officer_id' => $officer->id]);
        $event = DB::table('aip_activity_audit_events')->where('aip_activity_id', $activity->id)->first();
        $this->assertNotNull($event);
        $values = json_decode($event->new_values, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($subject->id, $values['subject_id']);
        $this->assertSame($officer->id, $values['officer_id']);
        $this->assertSame($actor->id, $event->actor_id);
    }

    public function test_it_rejects_an_unassigned_pair_in_the_same_office(): void
    {
        [$year, $officer, $subject] = $this->data();
        $this->assertRejected(['financial_year_id' => $year, 'activity_number' => '001', 'description' => 'Example', 'subject_id' => $subject->id, 'officer_id' => $officer->id], 'subject_id');
    }

    public function test_it_rejects_a_pair_from_different_offices(): void
    {
        [$year, $officer, , $otherSubject] = $this->data();
        $this->assertRejected(['financial_year_id' => $year, 'activity_number' => '001', 'description' => 'Example', 'subject_id' => $otherSubject->id, 'officer_id' => $officer->id], 'subject_id');
    }

    public function test_it_rejects_a_subject_without_an_officer(): void
    {
        [$year, , $subject] = $this->data();
        $this->assertRejected(['financial_year_id' => $year, 'activity_number' => '001', 'description' => 'Example', 'subject_id' => $subject->id], 'officer_id');
    }

    private function assertRejected(array $input, string $field): void
    {
        try {
            app(AipActivityService::class)->create($input);
            $this->fail('An invalid assignment should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_audit_events', 0);
    }
}
