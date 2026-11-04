<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FundAuditHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_a_guest_cannot_view_fund_audit_history(): void
    {
        $this->get('/fund-audit')->assertRedirect('/login');
    }

    public function test_a_regular_user_cannot_view_fund_audit_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/fund-audit')
            ->assertForbidden();
    }

    public function test_an_administrator_can_view_fund_audit_history(): void
    {
        $admin = $this->administrator();
        $service = app(FundService::class);

        $fund = $service->create([
            'code' => 'FUND-01',
            'name' => 'Original Fund',
            'is_active' => true,
        ], $admin);

        $service->update($fund, [
            'code' => 'FUND-01',
            'name' => 'Updated Fund',
            'is_active' => false,
        ], $admin);

        $this->actingAs($admin)
            ->get('/fund-audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('funds/audit')
                ->has('events.data', 2)
                ->where('events.data.0.action', 'updated')
                ->where('events.data.0.fund_code', 'FUND-01')
                ->where('events.data.0.fund_name', 'Updated Fund')
                ->where('events.data.0.actor_name', $admin->name)
                ->where('events.data.1.action', 'created')
            );
    }
}
