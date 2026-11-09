<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubjectAuditHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_subject_audit_history(): void
    {
        $this->get('/subject-audit')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_view_subject_audit_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/subject-audit')
            ->assertForbidden();
    }

    public function test_an_administrator_can_view_subject_audit_history(): void
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'is_admin' => true,
        ])->save();

        $office = Office::create([
            'code' => 'OFF-01',
            'name' => 'Test office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $service = app(SubjectService::class);

        $subject = $service->create([
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'Original name',
            'is_active' => true,
        ], $admin);

        $service->update($subject, [
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'Updated name',
            'is_active' => false,
        ], $admin);

        $this->actingAs($admin)
            ->get('/subject-audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('subjects/audit')
                ->has('events.data', 2)
                ->where('events.data.0.action', 'updated')
                ->where('events.data.0.actor_name', $admin->name)
                ->where('events.data.0.old_values.name', 'Original name')
                ->where('events.data.0.new_values.name', 'Updated name')
                ->where('events.data.0.new_values.is_active', false)
            );
    }
}