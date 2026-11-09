<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FinancialYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialYearAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_is_recorded_with_the_actor(): void
    {
        $actor = User::factory()->create();

        $financialYear = app(FinancialYearService::class)->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ], $actor);

        $event = DB::table('financial_year_audit_events')
            ->where('financial_year_id', $financialYear->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($actor->id, $event->actor_id);
        $this->assertSame('created', $event->action);
        $this->assertNull($event->old_values);

        $values = json_decode($event->new_values, true);

        $this->assertSame(2026, $values['year']);
        $this->assertSame('2026-01-01', $values['start_date']);
        $this->assertSame('2026-12-31', $values['end_date']);
        $this->assertFalse($values['is_closed']);
    }

    public function test_invalid_data_does_not_create_a_year_or_audit_event(): void
    {
        try {
            app(FinancialYearService::class)->create([
                'year' => 2026,
                'start_date' => '2026-12-31',
                'end_date' => '2026-01-01',
            ]);
        } catch (ValidationException $exception) {
            $this->assertDatabaseCount('financial_years', 0);
            $this->assertDatabaseCount('financial_year_audit_events', 0);

            return;
        }

        $this->fail('Invalid dates should have been rejected.');
    }
}