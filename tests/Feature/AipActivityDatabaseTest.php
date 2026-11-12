<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\FinancialYear;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AipActivityDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_activity_can_be_linked_to_a_financial_year(): void
    {
        $id = DB::table('financial_years')->insertGetId([
            'year' => 2025,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_closed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $activity = AipActivity::create([
            'financial_year_id' => $id,
            'activity_number' => '001',
            'description' => 'Example training programme',
            'location' => 'Provincial level',
        ]);

        $this->assertDatabaseHas('aip_activities', [
            'id' => $activity->id,
            'financial_year_id' => $id,
            'activity_number' => '001',
        ]);
        $this->assertInstanceOf(FinancialYear::class, $activity->financialYear);
        $this->assertSame($id, $activity->financialYear->id);
    }

    public function test_an_activity_requires_an_existing_financial_year(): void
    {
        $this->expectException(QueryException::class);

        AipActivity::create([
            'financial_year_id' => 999999,
            'activity_number' => 'EXAMPLE',
            'description' => 'Example activity',
        ]);
    }
}
