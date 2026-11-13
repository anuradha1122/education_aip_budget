<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\Office;
use App\Services\AipActivityOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AipActivityOfficeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function activity(bool $closed = false): AipActivity
    {
        $yearId = DB::table('financial_years')->insertGetId([
            'year' => 2025,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_closed' => $closed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return AipActivity::create([
            'financial_year_id' => $yearId,
            'activity_number' => '123',
            'description' => 'Science O/L seminar',
        ]);
    }

    private function office(string $code = 'ZONE-01'): Office
    {
        return Office::create([
            'code' => $code,
            'name' => 'Example office '.$code,
            'type' => 'zonal',
            'is_active' => true,
        ]);
    }

    public function test_it_links_an_office_to_an_activity(): void
    {
        $activity = $this->activity();
        $office = $this->office();

        $id = app(AipActivityOfficeService::class)->create([
            'aip_activity_id' => $activity->id,
            'office_id' => $office->id,
        ]);

        $this->assertDatabaseHas('aip_activity_offices', [
            'id' => $id,
            'aip_activity_id' => $activity->id,
            'office_id' => $office->id,
        ]);
    }

    public function test_it_rejects_a_duplicate_link(): void
    {
        $activity = $this->activity();
        $office = $this->office();

        $input = [
            'aip_activity_id' => $activity->id,
            'office_id' => $office->id,
        ];

        $service = app(AipActivityOfficeService::class);
        $service->create($input);

        try {
            $service->create($input);
            $this->fail('The duplicate link should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('office_id', $exception->errors());
        }

        $this->assertDatabaseCount('aip_activity_offices', 1);
    }

    public function test_it_rejects_a_link_for_a_closed_year(): void
    {
        $activity = $this->activity(true);
        $office = $this->office();

        try {
            app(AipActivityOfficeService::class)->create([
                'aip_activity_id' => $activity->id,
                'office_id' => $office->id,
            ]);
            $this->fail('A closed year should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('aip_activity_id', $exception->errors());
        }

        $this->assertDatabaseCount('aip_activity_offices', 0);
    }

    public function test_it_rejects_a_missing_activity(): void
    {
        $office = $this->office();

        try {
            app(AipActivityOfficeService::class)->create([
                'aip_activity_id' => 999999,
                'office_id' => $office->id,
            ]);
            $this->fail('A missing activity should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('aip_activity_id', $exception->errors());
        }

        $this->assertDatabaseCount('aip_activity_offices', 0);
    }

    public function test_it_rejects_a_missing_office(): void
    {
        $activity = $this->activity();

        try {
            app(AipActivityOfficeService::class)->create([
                'aip_activity_id' => $activity->id,
                'office_id' => 999999,
            ]);
            $this->fail('A missing office should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('office_id', $exception->errors());
        }

        $this->assertDatabaseCount('aip_activity_offices', 0);
    }
}