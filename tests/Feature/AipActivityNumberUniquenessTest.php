<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AipActivityNumberUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private function year(int $year): int
    {
        return DB::table('financial_years')->insertGetId([
            'year' => $year, 'start_date' => "$year-01-01", 'end_date' => "$year-12-31",
            'is_closed' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_duplicate_number_in_same_year_is_rejected_without_audit_event(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $year = $this->year(2025);
        $data = ['financial_year_id' => $year, 'activity_number' => '123', 'description' => 'Science seminar'];

        $this->actingAs($admin)->post('/aip-activities', $data)->assertSessionHasNoErrors();
        $this->post('/aip-activities', $data)->assertSessionHasErrors('activity_number');
        $this->assertDatabaseCount('aip_activities', 1);
        $this->assertDatabaseCount('aip_activity_audit_events', 1);
    }

    public function test_same_number_can_be_used_in_another_year(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
        foreach ([2025, 2026] as $year) {
            $this->post('/aip-activities', [
                'financial_year_id' => $this->year($year), 'activity_number' => '123', 'description' => 'Science seminar',
            ])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('aip_activities', 2);
    }

    public function test_database_rejects_duplicate_year_and_activity_number(): void
    {
        $year = $this->year(2025);
        $row = ['financial_year_id' => $year, 'activity_number' => '001', 'description' => 'Example', 'created_at' => now(), 'updated_at' => now()];
        DB::table('aip_activities')->insert($row);
        $this->expectException(QueryException::class);
        DB::table('aip_activities')->insert($row);
    }
}
