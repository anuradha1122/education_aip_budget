<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DesignationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_access_designations(): void
    {
        $this->get('/designations')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_manage_designations(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/designations')
            ->assertForbidden();

        $this->post('/designations', [
            'code' => 'DEO',
            'name' => 'Director of Education',
            'is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseCount('designations', 0);
    }

    public function test_an_administrator_can_view_and_search_designations(): void
    {
        Designation::create([
            'code' => 'DEO',
            'name' => 'Director of Education',
            'is_active' => true,
        ]);

        Designation::create([
            'code' => 'ADE',
            'name' => 'Assistant Director',
            'is_active' => true,
        ]);

        $this->actingAs($this->administrator())
            ->get('/designations?search=DEO')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('designations/index')
                ->where('filters.search', 'DEO')
                ->has('designations', 1)
                ->where('designations.0.code', 'DEO')
            );
    }

    public function test_an_administrator_can_create_a_designation_with_an_audit_event(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->post('/designations', [
                'code' => ' deo ',
                'name' => ' Director of Education ',
                'is_active' => true,
            ])
            ->assertRedirect(route('designations.index'))
            ->assertSessionHasNoErrors();

        $designation = Designation::query()
            ->where('code', 'DEO')
            ->firstOrFail();

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'name' => 'Director of Education',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('designation_audit_events', [
            'designation_id' => $designation->id,
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);
    }

    public function test_an_administrator_can_update_and_deactivate_a_designation(): void
    {
        $admin = $this->administrator();

        $designation = Designation::create([
            'code' => 'DEO',
            'name' => 'Director of Education',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put('/designations/'.$designation->id, [
                'code' => 'DEO',
                'name' => 'Updated designation',
                'is_active' => false,
            ])
            ->assertRedirect(route('designations.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'name' => 'Updated designation',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('designation_audit_events', [
            'designation_id' => $designation->id,
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