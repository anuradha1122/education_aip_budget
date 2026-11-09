<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_access_votes(): void
    {
        $this->get('/votes')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_manage_votes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/votes')
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/votes', [
                'code' => 'VOTE-01',
                'description' => 'Test vote',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('votes', 0);
        $this->assertDatabaseCount('vote_audit_events', 0);
    }

    public function test_an_administrator_can_view_votes(): void
    {
        $this->actingAs($this->administrator())
            ->get('/votes')
            ->assertOk();
    }

    public function test_an_administrator_can_create_a_vote_with_an_audit_event(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->post('/votes', [
                'code' => ' vote-01 ',
                'description' => 'Test vote',
                'is_active' => true,
            ])
            ->assertRedirect(route('votes.index'))
            ->assertSessionHasNoErrors();

        $vote = Vote::query()->where('code', 'VOTE-01')->firstOrFail();

        $this->assertDatabaseHas('vote_audit_events', [
            'vote_id' => $vote->id,
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);
    }

    public function test_duplicate_vote_codes_are_rejected(): void
    {
        $admin = $this->administrator();

        Vote::create([
            'code' => 'VOTE-01',
            'description' => 'Existing vote',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post('/votes', [
                'code' => ' vote-01 ',
                'description' => 'Another vote',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseCount('votes', 1);
    }

    public function test_an_administrator_can_deactivate_a_vote(): void
    {
        $admin = $this->administrator();

        $vote = Vote::create([
            'code' => 'VOTE-01',
            'description' => 'Test vote',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put("/votes/{$vote->id}", [
                'code' => 'VOTE-01',
                'description' => 'Test vote',
                'is_active' => false,
            ])
            ->assertRedirect(route('votes.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('votes', [
            'id' => $vote->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('vote_audit_events', [
            'vote_id' => $vote->id,
            'actor_id' => $admin->id,
            'action' => 'updated',
        ]);
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