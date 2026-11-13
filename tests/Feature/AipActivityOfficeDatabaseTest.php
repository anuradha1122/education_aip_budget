<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\Office;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AipActivityOfficeDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private function activity(): AipActivity
    {
        $yearId = DB::table('financial_years')->insertGetId([
            'year' => 2025,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_closed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return AipActivity::create([
            'financial_year_id' => $yearId,
            'activity_number' => '123',
            'description' => 'Science O/L seminar',
        ]);
    }

    private function office(string $code): Office
    {
        return Office::create([
            'code' => $code,
            'name' => 'Example office '.$code,
            'type' => 'zonal',
            'is_active' => true,
        ]);
    }

    public function test_one_activity_can_be_linked_to_multiple_offices(): void
    {
        $activity = $this->activity();
        $firstOffice = $this->office('ZONE-01');
        $secondOffice = $this->office('ZONE-02');

        foreach ([$firstOffice, $secondOffice] as $office) {
            DB::table('aip_activity_offices')->insert([
                'aip_activity_id' => $activity->id,
                'office_id' => $office->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertDatabaseCount('aip_activity_offices', 2);

        $this->assertDatabaseHas('aip_activity_offices', [
            'aip_activity_id' => $activity->id,
            'office_id' => $firstOffice->id,
        ]);

        $this->assertDatabaseHas('aip_activity_offices', [
            'aip_activity_id' => $activity->id,
            'office_id' => $secondOffice->id,
        ]);
    }

    public function test_an_office_cannot_be_linked_twice_to_the_same_activity(): void
    {
        $activity = $this->activity();
        $office = $this->office('ZONE-01');

        $link = [
            'aip_activity_id' => $activity->id,
            'office_id' => $office->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('aip_activity_offices')->insert($link);

        $this->expectException(QueryException::class);

        DB::table('aip_activity_offices')->insert($link);
    }

    public function test_a_link_requires_an_existing_office(): void
    {
        $activity = $this->activity();

        $this->expectException(QueryException::class);

        DB::table('aip_activity_offices')->insert([
            'aip_activity_id' => $activity->id,
            'office_id' => 999999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}