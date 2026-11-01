<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfficeAuditHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        return $user;
    }

    public function test_a_guest_cannot_view_office_audit_history(): void
    {
        $this->get('/office-audit')->assertRedirect('/login');
    }

    public function test_a_regular_user_cannot_view_office_audit_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/office-audit')
            ->assertForbidden();
    }

    public function test_an_administrator_can_view_office_audit_history(): void
    {
        $admin = $this->administrator();

        $office = Office::create([
            'code' => 'TEST-01',
            'name' => 'Test Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        DB::table('office_audit_events')->insert([
            'office_id' => $office->id,
            'actor_id' => $admin->id,
            'action' => 'created',
            'old_values' => null,
            'new_values' => json_encode([
                'code' => 'TEST-01',
                'name' => 'Test Office',
                'type' => 'zonal',
                'is_active' => true,
            ]),
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/office-audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('offices/audit')
                ->has('events.data', 1)
                ->where('events.data.0.office_code', 'TEST-01')
                ->where('events.data.0.action', 'created')
                ->where('events.data.0.actor_name', $admin->name)
            );
    }
}
