<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialYearManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_access_financial_years(): void
    {
        $this->get('/financial-years')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_create_a_financial_year(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/financial-years')
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/financial-years', [
                'year' => 2026,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('financial_years', 0);
        $this->assertDatabaseCount('financial_year_audit_events', 0);
    }

    public function test_an_administrator_can_view_financial_years(): void
    {
        $this->actingAs($this->administrator())
            ->get('/financial-years')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('financial-years/index')
                ->has('financialYears')
            );
    }

    public function test_an_administrator_can_create_a_year_with_an_audit_event(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->post('/financial-years', [
                'year' => 2026,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ])
            ->assertRedirect(route('financial-years.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('financial_years', [
            'year' => 2026,
            'is_closed' => false,
        ]);

        $this->assertDatabaseHas('financial_year_audit_events', [
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);
    }

    public function test_invalid_dates_are_rejected(): void
    {
        $this->actingAs($this->administrator())
            ->post('/financial-years', [
                'year' => 2026,
                'start_date' => '2026-12-31',
                'end_date' => '2026-01-01',
            ])
            ->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('financial_years', 0);
        $this->assertDatabaseCount('financial_year_audit_events', 0);
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