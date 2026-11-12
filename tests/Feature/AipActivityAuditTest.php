<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AipActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AipActivityAuditTest extends TestCase
{
    use RefreshDatabase;

    private function year(bool $closed): int
    {
        return DB::table('financial_years')->insertGetId([
            'year' => 2025, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31',
            'is_closed' => $closed, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_creation_records_the_actor_and_activity_values(): void
    {
        $actor = User::factory()->create();
        $year = $this->year(false);
        $activity = app(AipActivityService::class)->create([
            'financial_year_id' => $year, 'activity_number' => '001',
            'description' => 'Example activity', 'location' => 'Provincial level',
        ], $actor);
        $event = DB::table('aip_activity_audit_events')->where('aip_activity_id', $activity->id)->first();
        $this->assertNotNull($event);
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame('created', $event->action);
        $this->assertNull($event->old_values);
        $values = json_decode($event->new_values, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('001', $values['activity_number']);
        $this->assertSame('Example activity', $values['description']);
        $this->assertSame('Provincial level', $values['location']);
        $this->assertSame($year, $values['financial_year_id']);
    }

    public function test_a_closed_year_does_not_create_an_activity_or_audit_event(): void
    {
        $year = $this->year(true);
        try {
            app(AipActivityService::class)->create([
                'financial_year_id' => $year, 'activity_number' => '001', 'description' => 'Example',
            ], User::factory()->create());
            $this->fail('A closed year should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('financial_year_id', $exception->errors());
        }
        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_audit_events', 0);
    }
}
