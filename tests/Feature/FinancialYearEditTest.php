<?php

namespace Tests\Feature;

use App\Models\FinancialYear;
use App\Models\User;
use App\Services\FinancialYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialYearEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_edit_an_open_year_with_audit(): void
    {
        $admin = $this->administrator();

        $year = app(FinancialYearService::class)->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ], $admin);

        $this->actingAs($admin)
            ->put("/financial-years/{$year->id}", [
                'year' => 2026,
                'start_date' => '2026-02-01',
                'end_date' => '2026-12-31',
            ])
            ->assertRedirect(route('financial-years.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            '2026-02-01',
            $year->fresh()->start_date->toDateString()
        );

        $event = DB::table('financial_year_audit_events')
            ->where('financial_year_id', $year->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($admin->id, $event->actor_id);

        $oldValues = json_decode($event->old_values, true);
        $newValues = json_decode($event->new_values, true);

        $this->assertSame('2026-01-01', $oldValues['start_date']);
        $this->assertSame('2026-02-01', $newValues['start_date']);
    }

    public function test_a_closed_year_cannot_be_edited(): void
    {
        $admin = $this->administrator();

        $year = FinancialYear::create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => true,
        ]);

        $this->actingAs($admin)
            ->put("/financial-years/{$year->id}", [
                'year' => 2026,
                'start_date' => '2026-02-01',
                'end_date' => '2026-12-31',
            ])
            ->assertSessionHasErrors('year');

        $this->assertSame(
            '2026-01-01',
            $year->fresh()->start_date->toDateString()
        );

        $this->assertDatabaseCount('financial_year_audit_events', 0);
    }

    public function test_a_regular_user_cannot_edit_a_year(): void
    {
        $user = User::factory()->create();

        $year = FinancialYear::create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
        ]);

        $this->actingAs($user)
            ->put("/financial-years/{$year->id}", [
                'year' => 2026,
                'start_date' => '2026-02-01',
                'end_date' => '2026-12-31',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('financial_year_audit_events', 0);
    }

    public function test_an_unchanged_year_does_not_create_an_update_event(): void
    {
        $admin = $this->administrator();
        $service = app(FinancialYearService::class);

        $year = $service->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ], $admin);

        $this->actingAs($admin)
            ->put("/financial-years/{$year->id}", [
                'year' => 2026,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ])
            ->assertRedirect(route('financial-years.index'));

        $this->assertDatabaseCount('financial_year_audit_events', 1);
    }

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        return $user;
    }
}