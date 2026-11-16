<?php

namespace Tests\Feature;

use App\Models\AipActivity;
use App\Models\Fund;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AipFundingEntryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function activity(bool $closed = false): AipActivity
    {
        $yearId = DB::table('financial_years')->insertGetId([
            'year' => 2027,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_closed' => $closed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return AipActivity::create([
            'financial_year_id' => $yearId,
            'activity_number' => '123',
            'description' => 'Science seminar',
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_guests_and_regular_users_cannot_manage_entries(): void
    {
        $this->get('/aip-funding-entries')->assertRedirect(route('login'));
        $this->post('/aip-funding-entries', [])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());

        $this->get('/aip-funding-entries')->assertForbidden();
        $this->post('/aip-funding-entries', [])->assertForbidden();
    }

    public function test_admin_can_save_and_view_an_audited_entry(): void
    {
        $activity = $this->activity();

        $vote = Vote::create([
            'code' => 'VOTE-01',
            'description' => 'Example vote',
            'is_active' => true,
        ]);

        $fund = Fund::create([
            'code' => 'PF',
            'name' => 'Example fund',
            'is_active' => true,
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/aip-funding-entries', [
                'aip_activity_id' => $activity->id,
                'vote_id' => $vote->id,
                'fund_id' => $fund->id,
            ])
            ->assertRedirect(route('aip-funding-entries.index'))
            ->assertSessionHasNoErrors();

        $entry = DB::table('aip_activity_funding_entries')
            ->where('aip_activity_id', $activity->id)
            ->where('vote_id', $vote->id)
            ->where('fund_id', $fund->id)
            ->first();

        $this->assertNotNull($entry);

        $this->assertDatabaseHas('aip_funding_entry_audit_events', [
            'funding_entry_id' => $entry->id,
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);

        $this->get('/aip-funding-entries')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('aip-funding-entries/index')
                ->has('entries.data', 1)
                ->where('entries.data.0.activity_number', '123')
                ->where('entries.data.0.vote_code', 'VOTE-01')
                ->where('entries.data.0.fund_code', 'PF'));
    }

    public function test_admin_cannot_add_an_entry_to_a_closed_year(): void
    {
        $activity = $this->activity(true);

        $vote = Vote::create([
            'code' => 'VOTE-01',
            'description' => 'Example vote',
            'is_active' => true,
        ]);

        $fund = Fund::create([
            'code' => 'PF',
            'name' => 'Example fund',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->post('/aip-funding-entries', [
                'aip_activity_id' => $activity->id,
                'vote_id' => $vote->id,
                'fund_id' => $fund->id,
            ])
            ->assertSessionHasErrors('aip_activity_id');

        $this->assertDatabaseCount('aip_activity_funding_entries', 0);
        $this->assertDatabaseCount('aip_funding_entry_audit_events', 0);
    }
}