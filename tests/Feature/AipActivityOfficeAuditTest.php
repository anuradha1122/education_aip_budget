<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\Office;
use App\Models\User;
use App\Services\AipActivityOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AipActivityOfficeAuditTest extends TestCase
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

    private function office(): Office
    {
        return Office::create([
            'code' => 'ZONE-01',
            'name' => 'Example office',
            'type' => 'zonal',
            'is_active' => true,
        ]);
    }

    public function test_link_creation_records_the_actor_and_values(): void
    {
        $activity = $this->activity();
        $office = $this->office();
        $actor = User::factory()->create();

        $linkId = app(AipActivityOfficeService::class)->create([
            'aip_activity_id' => $activity->id,
            'office_id' => $office->id,
        ], $actor);

        $event = DB::table('aip_activity_office_audit_events')
            ->where('aip_activity_office_id', $linkId)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($actor->id, $event->actor_id);
        $this->assertSame('created', $event->action);

        $values = json_decode($event->new_values, true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals($activity->id, $values['aip_activity_id']);
        $this->assertEquals($activity->financial_year_id, $values['financial_year_id']);
        $this->assertEquals($office->id, $values['office_id']);
    }

    public function test_a_rejected_link_does_not_create_an_audit_event(): void
    {
        $activity = $this->activity(true);
        $office = $this->office();

        try {
            app(AipActivityOfficeService::class)->create([
                'aip_activity_id' => $activity->id,
                'office_id' => $office->id,
            ], User::factory()->create());

            $this->fail('A closed financial year should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('aip_activity_id', $exception->errors());
        }

        $this->assertDatabaseCount('aip_activity_offices', 0);
        $this->assertDatabaseCount('aip_activity_office_audit_events', 0);
    }
}