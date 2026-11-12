<?php

namespace Tests\Feature;

use App\Services\AipActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AipActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function year(bool $closed): int
    {
        return DB::table('financial_years')->insertGetId([
            'year' => 2025, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31',
            'is_closed' => $closed, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_it_creates_an_activity_for_an_open_year_and_trims_input(): void
    {
        $yearId = $this->year(false);
        $activity = app(AipActivityService::class)->create([
            'financial_year_id' => $yearId,
            'activity_number' => ' 001 ',
            'description' => ' Example activity ',
            'location' => ' Provincial level ',
        ]);
        $this->assertDatabaseHas('aip_activities', [
            'id' => $activity->id, 'financial_year_id' => $yearId,
            'activity_number' => '001', 'description' => 'Example activity', 'location' => 'Provincial level',
        ]);
    }

    public function test_it_rejects_a_closed_financial_year(): void
    {
        $yearId = $this->year(true);
        try {
            app(AipActivityService::class)->create([
                'financial_year_id' => $yearId, 'activity_number' => '001', 'description' => 'Example activity',
            ]);
            $this->fail('A closed year should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('financial_year_id', $exception->errors());
        }
        $this->assertDatabaseCount('aip_activities', 0);
    }

    public function test_it_rejects_a_missing_financial_year(): void
    {
        try {
            app(AipActivityService::class)->create([
                'financial_year_id' => 999999, 'activity_number' => '001', 'description' => 'Example activity',
            ]);
            $this->fail('A missing year should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('financial_year_id', $exception->errors());
        }
        $this->assertDatabaseCount('aip_activities', 0);
    }

    public function test_it_requires_a_description_and_activity_number(): void
    {
        try {
            app(AipActivityService::class)->create([
                'financial_year_id' => $this->year(false), 'activity_number' => '  ', 'description' => '  ',
            ]);
            $this->fail('Missing fields should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('activity_number', $exception->errors());
            $this->assertArrayHasKey('description', $exception->errors());
        }
        $this->assertDatabaseCount('aip_activities', 0);
    }
}
