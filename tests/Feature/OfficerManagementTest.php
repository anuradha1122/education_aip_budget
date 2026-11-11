<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfficerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_access_officers(): void
    {
        $this->get('/officers')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_manage_officers(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/officers')
            ->assertForbidden();

        $this->post('/officers', [])
            ->assertForbidden();

        $this->assertDatabaseCount('officers', 0);
    }

    public function test_an_administrator_can_view_and_search_officers(): void
    {
        $office = $this->office();
        $designation = $this->designation();

        Officer::create([
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => 'Nimal Perera',
            'is_active' => true,
        ]);

        Officer::create([
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => 'Kamal Silva',
            'is_active' => true,
        ]);

        $this->actingAs($this->administrator())
            ->get('/officers?search=Nimal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('officers/index')
                ->where('filters.search', 'Nimal')
                ->has('officers', 1)
                ->where('officers.0.name', 'Nimal Perera')
                ->where('officers.0.office.code', 'OFF-01')
                ->where('officers.0.designation.code', 'DES-01')
                ->has('offices', 1)
                ->has('designations', 1)
            );
    }

    public function test_an_administrator_can_create_an_officer_with_an_audit_event(): void
    {
        $admin = $this->administrator();
        $office = $this->office();
        $designation = $this->designation();

        $this->actingAs($admin)
            ->post('/officers', [
                'office_id' => $office->id,
                'designation_id' => $designation->id,
                'name' => ' Test Officer ',
                'is_active' => true,
            ])
            ->assertRedirect(route('officers.index'))
            ->assertSessionHasNoErrors();

        $officer = Officer::query()
            ->where('name', 'Test Officer')
            ->firstOrFail();

        $this->assertDatabaseHas('officers', [
            'id' => $officer->id,
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('officer_audit_events', [
            'officer_id' => $officer->id,
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);
    }

    public function test_an_administrator_can_update_and_deactivate_an_officer(): void
    {
        $admin = $this->administrator();
        $office = $this->office();
        $designation = $this->designation();

        $officer = Officer::create([
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => 'Old name',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put('/officers/'.$officer->id, [
                'office_id' => $office->id,
                'designation_id' => $designation->id,
                'name' => 'Updated name',
                'is_active' => false,
            ])
            ->assertRedirect(route('officers.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('officers', [
            'id' => $officer->id,
            'name' => 'Updated name',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('officer_audit_events', [
            'officer_id' => $officer->id,
            'actor_id' => $admin->id,
            'action' => 'updated',
        ]);
    }

    private function office(): Office
    {
        return Office::create([
            'code' => 'OFF-01',
            'name' => 'Test Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);
    }

    private function designation(): Designation
    {
        return Designation::create([
            'code' => 'DES-01',
            'name' => 'Test Designation',
            'is_active' => true,
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