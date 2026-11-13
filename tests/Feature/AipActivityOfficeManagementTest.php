<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AipActivityOfficeManagementTest extends TestCase
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

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_guests_and_regular_users_cannot_manage_links(): void
    {
        $this->get('/aip-activity-offices')
            ->assertRedirect(route('login'));

        $this->post('/aip-activity-offices', [])
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());

        $this->get('/aip-activity-offices')->assertForbidden();
        $this->post('/aip-activity-offices', [])->assertForbidden();
    }

    public function test_an_administrator_can_link_an_office_and_view_it(): void
    {
        $activity = $this->activity();
        $office = $this->office();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/aip-activity-offices', [
                'aip_activity_id' => $activity->id,
                'office_id' => $office->id,
            ])
            ->assertRedirect(route('aip-activity-offices.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('aip_activity_offices', [
            'aip_activity_id' => $activity->id,
            'office_id' => $office->id,
        ]);

        $this->assertDatabaseHas('aip_activity_office_audit_events', [
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);

        $this->get('/aip-activity-offices')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('aip-activity-offices/index')
                ->has('links.data', 1)
                ->where('links.data.0.activity_number', '123')
                ->where('links.data.0.office_code', 'ZONE-01'));
    }

    public function test_a_duplicate_link_is_rejected(): void
    {
        $activity = $this->activity();
        $office = $this->office();

        $input = [
            'aip_activity_id' => $activity->id,
            'office_id' => $office->id,
        ];

        $this->actingAs($this->admin())
            ->post('/aip-activity-offices', $input)
            ->assertSessionHasNoErrors();

        $this->post('/aip-activity-offices', $input)
            ->assertSessionHasErrors('office_id');

        $this->assertDatabaseCount('aip_activity_offices', 1);
        $this->assertDatabaseCount('aip_activity_office_audit_events', 1);
    }

    public function test_a_closed_year_cannot_receive_a_new_link(): void
    {
        $activity = $this->activity(true);
        $office = $this->office();

        $this->actingAs($this->admin())
            ->post('/aip-activity-offices', [
                'aip_activity_id' => $activity->id,
                'office_id' => $office->id,
            ])
            ->assertSessionHasErrors('aip_activity_id');

        $this->assertDatabaseCount('aip_activity_offices', 0);
        $this->assertDatabaseCount('aip_activity_office_audit_events', 0);
    }
}