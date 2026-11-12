<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Models\Subject;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AipActivityRelationsTest extends TestCase
{
    use RefreshDatabase;

    private function yearId(): int
    {
        return DB::table('financial_years')->insertGetId([
            'year' => 2025, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31',
            'is_closed' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_an_activity_can_reference_a_subject_and_officer(): void
    {
        $office = Office::create(['code' => 'OFF-01', 'name' => 'Example office', 'type' => 'zonal', 'is_active' => true]);
        $designation = Designation::create(['code' => 'DES-01', 'name' => 'Example designation', 'is_active' => true]);
        $subject = Subject::create(['office_id' => $office->id, 'code' => 'SUB-01', 'name' => 'Example subject', 'is_active' => true]);
        $officer = Officer::create(['office_id' => $office->id, 'designation_id' => $designation->id, 'name' => 'Example officer', 'is_active' => true]);
        $activity = AipActivity::create([
            'financial_year_id' => $this->yearId(), 'activity_number' => '001',
            'description' => 'Example activity', 'subject_id' => $subject->id, 'officer_id' => $officer->id,
        ]);
        $this->assertDatabaseHas('aip_activities', ['id' => $activity->id, 'subject_id' => $subject->id, 'officer_id' => $officer->id]);
        $this->assertSame($subject->id, $activity->subject->id);
        $this->assertSame($officer->id, $activity->officer->id);
        $this->assertSame($designation->id, $activity->officer->designation->id);
    }

    public function test_subject_and_officer_are_optional_for_existing_activities(): void
    {
        $activity = AipActivity::create([
            'financial_year_id' => $this->yearId(), 'activity_number' => '001', 'description' => 'Existing activity',
        ]);
        $this->assertNull($activity->fresh()->subject_id);
        $this->assertNull($activity->fresh()->officer_id);
    }

    public function test_an_activity_cannot_reference_a_missing_subject(): void
    {
        $this->expectException(QueryException::class);
        AipActivity::create([
            'financial_year_id' => $this->yearId(), 'activity_number' => '001',
            'description' => 'Example activity', 'subject_id' => 999999,
        ]);
    }

    public function test_an_activity_cannot_reference_a_missing_officer(): void
    {
        $this->expectException(QueryException::class);
        AipActivity::create([
            'financial_year_id' => $this->yearId(), 'activity_number' => '001',
            'description' => 'Example activity', 'officer_id' => 999999,
        ]);
    }
}
