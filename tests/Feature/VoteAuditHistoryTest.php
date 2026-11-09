<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\VoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VoteAuditHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_vote_audit_history(): void
    {
        $this->get('/vote-audit')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_view_vote_audit_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/vote-audit')
            ->assertForbidden();
    }

    public function test_an_administrator_can_view_vote_audit_history(): void
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        $service = app(VoteService::class);

        $vote = $service->create([
            'code' => 'VOTE-01',
            'description' => 'Original description',
            'is_active' => true,
        ], $admin);

        $service->update($vote, [
            'code' => 'VOTE-01',
            'description' => 'Updated description',
            'is_active' => false,
        ], $admin);

        $this->actingAs($admin)
            ->get('/vote-audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('votes/audit')
                ->has('events.data', 2)
                ->where('events.data.0.action', 'updated')
                ->where('events.data.0.actor_name', $admin->name)
                ->where('events.data.0.old_values.description', 'Original description')
                ->where('events.data.0.new_values.description', 'Updated description')
                ->where('events.data.0.new_values.is_active', false)
            );
    }
}