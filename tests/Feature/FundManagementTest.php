<?php

namespace Tests\Feature;

use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FundManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_a_guest_cannot_access_funds(): void
    {
        $this->get('/funds')->assertRedirect('/login');
    }

    public function test_a_regular_user_cannot_manage_funds(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/funds')->assertForbidden();

        $this->actingAs($user)->post('/funds', [
            'code' => 'TEST',
            'name' => 'Test Fund',
            'is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseCount('funds', 0);
        $this->assertDatabaseCount('fund_audit_events', 0);
    }

    public function test_an_administrator_can_view_and_search_funds(): void
    {
        Fund::create([
            'code' => 'FUND-01',
            'name' => 'Example Fund',
            'is_active' => true,
        ]);

        Fund::create([
            'code' => 'FUND-02',
            'name' => 'Another Fund',
            'is_active' => true,
        ]);

        $this->actingAs($this->administrator())
            ->get('/funds?search=Example')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('funds/index')
                ->has('funds', 1)
                ->where('funds.0.code', 'FUND-01')
            );
    }

    public function test_an_administrator_can_create_a_fund_with_an_audit_event(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->post('/funds', [
                'code' => ' fund-01 ',
                'name' => ' Example Fund ',
                'is_active' => true,
            ])
            ->assertRedirect('/funds');

        $fund = Fund::where('code', 'FUND-01')->firstOrFail();

        $this->assertSame('Example Fund', $fund->name);

        $this->assertDatabaseHas('fund_audit_events', [
            'fund_id' => $fund->id,
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);
    }

    public function test_duplicate_fund_codes_are_rejected(): void
    {
        Fund::create([
            'code' => 'FUND-01',
            'name' => 'Existing Fund',
            'is_active' => true,
        ]);

        $this->actingAs($this->administrator())
            ->from('/funds')
            ->post('/funds', [
                'code' => 'fund-01',
                'name' => 'Duplicate Fund',
                'is_active' => true,
            ])
            ->assertRedirect('/funds')
            ->assertSessionHasErrors('code');

        $this->assertDatabaseCount('funds', 1);
        $this->assertDatabaseCount('fund_audit_events', 0);
    }

    public function test_an_administrator_can_update_and_deactivate_a_fund(): void
    {
        $admin = $this->administrator();

        $fund = Fund::create([
            'code' => 'FUND-01',
            'name' => 'Original Fund',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put("/funds/{$fund->id}", [
                'code' => 'FUND-01',
                'name' => 'Updated Fund',
                'is_active' => false,
            ])
            ->assertRedirect('/funds');

        $this->assertDatabaseHas('funds', [
            'id' => $fund->id,
            'name' => 'Updated Fund',
            'is_active' => false,
        ]);

        $event = DB::table('fund_audit_events')
            ->where('fund_id', $fund->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame('updated', $event->action);
        $this->assertSame(
            'Original Fund',
            json_decode($event->old_values, true)['name']
        );
        $this->assertSame(
            'Updated Fund',
            json_decode($event->new_values, true)['name']
        );
    }

    public function test_an_unchanged_fund_does_not_create_an_audit_event(): void
    {
        $fund = Fund::create([
            'code' => 'FUND-01',
            'name' => 'Test Fund',
            'is_active' => true,
        ]);

        $this->actingAs($this->administrator())
            ->put("/funds/{$fund->id}", [
                'code' => 'FUND-01',
                'name' => 'Test Fund',
                'is_active' => true,
            ])
            ->assertRedirect('/funds');

        $this->assertDatabaseCount('fund_audit_events', 0);
    }
}
