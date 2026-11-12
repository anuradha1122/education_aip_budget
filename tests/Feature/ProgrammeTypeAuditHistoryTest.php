<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProgrammeTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProgrammeTypeAuditHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_programme_type_audit_history(): void
    {
        $this->get('/programme-types/audit')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_view_programme_type_audit_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/programme-types/audit')
            ->assertForbidden();
    }

    public function test_an_administrator_can_view_programme_type_audit_history(): void
    {
        $admin = $this->administrator();

        $programmeType = app(ProgrammeTypeService::class)->create([
            'code' => 'TYPE-01',
            'name' => 'Original type',
            'is_active' => true,
        ], $admin);

        app(ProgrammeTypeService::class)->update($programmeType, [
            'code' => 'TYPE-02',
            'name' => 'Updated type',
            'is_active' => false,
        ], $admin);

        $this->actingAs($admin)
            ->get('/programme-types/audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('programme-types/audit')
                ->has('events', 2)
                ->where('events.0.programme_type_id', $programmeType->id)
                ->where('events.0.action', 'updated')
                ->where('events.0.actor_name', $admin->name)
                ->where('events.0.old_values.code', 'TYPE-01')
                ->where('events.0.old_values.name', 'Original type')
                ->where('events.0.new_values.code', 'TYPE-02')
                ->where('events.0.new_values.name', 'Updated type')
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