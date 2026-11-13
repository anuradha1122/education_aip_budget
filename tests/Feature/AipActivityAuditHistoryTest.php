<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AipActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AipActivityAuditHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_regular_users_cannot_view_audit_history(): void
    {
        $this->get('/aip-activities/audit')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/aip-activities/audit')->assertForbidden();
    }

    public function test_an_administrator_can_view_the_created_event(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $year = DB::table('financial_years')->insertGetId([
            'year' => 2025, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31',
            'is_closed' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(AipActivityService::class)->create([
            'financial_year_id' => $year, 'activity_number' => '001', 'description' => 'Example activity',
        ], $admin);
        $this->actingAs($admin)->get('/aip-activities/audit')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('aip-activities/audit')
                ->has('events.data', 1)->where('events.data.0.current_activity_number', '001')
                ->where('events.data.0.actor_name', $admin->name)->where('events.data.0.action', 'created')
                ->has('events.data.0.new_values'));
    }
}
