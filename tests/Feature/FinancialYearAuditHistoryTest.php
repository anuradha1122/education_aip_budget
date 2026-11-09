<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FinancialYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialYearAuditHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_audit_history(): void
    {
        $this->get('/financial-year-audit')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_view_audit_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/financial-year-audit')
            ->assertForbidden();
    }

    public function test_an_administrator_can_view_creation_history(): void
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        app(FinancialYearService::class)->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ], $admin);

        $this->actingAs($admin)
            ->get('/financial-year-audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('financial-years/audit')
                ->has('events.data', 1)
                ->where('events.data.0.action', 'created')
                ->where('events.data.0.actor_name', $admin->name)
                ->where('events.data.0.old_values', null)
                ->where('events.data.0.new_values.year', 2026)
                ->where('events.data.0.new_values.start_date', '2026-01-01')
                ->where('events.data.0.new_values.is_closed', false)
            );
    }
}