<?php

namespace Tests\Feature;

use App\Models\ProgrammeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProgrammeTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_access_programme_types(): void
    {
        $this->get('/programme-types')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_manage_programme_types(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/programme-types')
            ->assertForbidden();

        $this->post('/programme-types', [
            'code' => 'TEST-01',
            'name' => 'Test type',
            'is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseCount('programme_types', 0);
    }

    public function test_an_administrator_can_view_and_search_programme_types(): void
    {
        ProgrammeType::create([
            'code' => 'TYPE-01',
            'name' => 'First type',
            'is_active' => true,
        ]);

        ProgrammeType::create([
            'code' => 'TYPE-02',
            'name' => 'Second type',
            'is_active' => true,
        ]);

        $this->actingAs($this->administrator())
            ->get('/programme-types?search=TYPE-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('programme-types/index')
                ->where('filters.search', 'TYPE-01')
                ->has('programmeTypes', 1)
                ->where('programmeTypes.0.code', 'TYPE-01')
            );
    }

    public function test_an_administrator_can_create_a_type_with_an_audit_event(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->post('/programme-types', [
                'code' => ' test-01 ',
                'name' => ' Test type ',
                'is_active' => true,
            ])
            ->assertRedirect(route('programme-types.index'))
            ->assertSessionHasNoErrors();

        $programmeType = ProgrammeType::query()
            ->where('code', 'TEST-01')
            ->firstOrFail();

        $this->assertDatabaseHas('programme_types', [
            'id' => $programmeType->id,
            'name' => 'Test type',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('programme_type_audit_events', [
            'programme_type_id' => $programmeType->id,
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);
    }

    public function test_an_administrator_can_update_and_deactivate_a_type(): void
    {
        $admin = $this->administrator();

        $programmeType = ProgrammeType::create([
            'code' => 'TEST-01',
            'name' => 'Old name',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put('/programme-types/'.$programmeType->id, [
                'code' => 'TEST-01',
                'name' => 'Updated name',
                'is_active' => false,
            ])
            ->assertRedirect(route('programme-types.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('programme_types', [
            'id' => $programmeType->id,
            'name' => 'Updated name',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('programme_type_audit_events', [
            'programme_type_id' => $programmeType->id,
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