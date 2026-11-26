<?php

namespace Tests\Feature;

use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialYearRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_provincial_admin_can_manage_financial_years_and_user_accounts(): void
    {
        $user = User::factory()->create();

        DB::table('user_access_assignments')->insert([
            'user_id' => $user->id,
            'role' => 'provincial_admin',
            'office_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/financial-years')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.manage_financial_years', true)
                ->where('permissions.manage_master_data', false)
            );

        $this->post('/financial-years', [
            'year' => 2031,
            'start_date' => '2031-01-01',
            'end_date' => '2031-12-31',
        ])->assertRedirect(route('financial-years.index'))
            ->assertSessionHasNoErrors();

        $year = FinancialYear::where('year', 2031)->firstOrFail();

        $this->get("/financial-years/{$year->id}/edit")->assertOk();

        $this->put("/financial-years/{$year->id}", [
            'year' => 2031,
            'start_date' => '2031-01-01',
            'end_date' => '2031-12-30',
        ])->assertRedirect(route('financial-years.index'))
            ->assertSessionHasNoErrors();

        $this->patch("/financial-years/{$year->id}/close")
            ->assertRedirect(route('financial-years.index'))
            ->assertSessionHasNoErrors();

        $this->assertTrue($year->fresh()->is_closed);

        $this->patch("/financial-years/{$year->id}/reopen", [
            'reason' => 'Correct approved records',
        ])->assertRedirect(route('financial-years.index'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($year->fresh()->is_closed);
        $this->get('/financial-year-audit')->assertOk();
        $this->get('/user-management')->assertOk();
        $this->get('/offices')->assertOk();

        $this->assertDatabaseHas('financial_year_audit_events', [
            'financial_year_id' => $year->id,
            'actor_id' => $user->id,
            'action' => 'reopened',
        ]);
    }

    public function test_zonal_admin_cannot_access_financial_year_routes(): void
    {
        $user = User::factory()->create();

        $officeId = DB::table('offices')->insertGetId([
            'code' => 'ZONE-TEST',
            'name' => 'Test Zone',
            'type' => 'zonal',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_access_assignments')->insert([
            'user_id' => $user->id,
            'role' => 'zonal_admin',
            'office_id' => $officeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $year = FinancialYear::create([
            'year' => 2032,
            'start_date' => '2032-01-01',
            'end_date' => '2032-12-31',
            'is_closed' => false,
        ]);

        $this->actingAs($user);

        $this->get('/financial-years')->assertForbidden();
        $this->get('/financial-year-audit')->assertForbidden();
        $this->get("/financial-years/{$year->id}/edit")->assertForbidden();
        $this->post('/financial-years', [])->assertForbidden();
        $this->put("/financial-years/{$year->id}", [])->assertForbidden();
        $this->patch("/financial-years/{$year->id}/close")->assertForbidden();
        $this->patch("/financial-years/{$year->id}/reopen", [
            'reason' => 'Test',
        ])->assertForbidden();

        $this->assertFalse($year->fresh()->is_closed);
    }

    public function test_system_admin_retains_access_and_guest_is_redirected(): void
    {
        $this->get('/financial-years')->assertRedirect(route('login'));

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)
            ->get('/financial-years')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.manage_financial_years', true)
            );

        $this->get('/financial-year-audit')->assertOk();
    }
}