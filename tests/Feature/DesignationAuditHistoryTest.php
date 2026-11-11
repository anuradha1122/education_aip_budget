<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DesignationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DesignationAuditHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_designation_audit_history(): void
    {
        $this->get('/designations/audit')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_view_designation_audit_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/designations/audit')
            ->assertForbidden();
    }

    public function test_an_administrator_can_view_designation_audit_history(): void
    {
        $admin = $this->administrator();

        $designation = app(DesignationService::class)->create([
            'code' => 'DEO',
            'name' => 'Director of Education',
            'is_active' => true,
        ], $admin);

        app(DesignationService::class)->update($designation, [
            'code' => 'DEO',
            'name' => 'Updated name',
            'is_active' => false,
        ], $admin);

        $this->actingAs($admin)
            ->get('/designations/audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('designations/audit')
                ->has('events', 2)
                ->where('events.0.action', 'updated')
                ->where('events.0.actor_name', $admin->name)
                ->where('events.0.old_values.name', 'Director of Education')
                ->where('events.0.new_values.name', 'Updated name')
                ->where('events.0.new_values.is_active', false)
                ->where('events.1.action', 'created')
            );
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