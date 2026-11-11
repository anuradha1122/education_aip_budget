<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Office;
use App\Models\User;
use App\Services\OfficerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfficerAuditHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_officer_audit_history(): void
    {
        $this->get('/officers/audit')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_view_officer_audit_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/officers/audit')
            ->assertForbidden();
    }

    public function test_an_administrator_can_view_officer_audit_history(): void
    {
        $admin = $this->administrator();

        $firstOffice = $this->office('OFF-01');
        $secondOffice = $this->office('OFF-02');

        $firstDesignation = $this->designation('DES-01');
        $secondDesignation = $this->designation('DES-02');

        $officer = app(OfficerService::class)->create([
            'office_id' => $firstOffice->id,
            'designation_id' => $firstDesignation->id,
            'name' => 'Test Officer',
            'is_active' => true,
        ], $admin);

        app(OfficerService::class)->update($officer, [
            'office_id' => $secondOffice->id,
            'designation_id' => $secondDesignation->id,
            'name' => 'Updated Officer',
            'is_active' => false,
        ], $admin);

        $this->actingAs($admin)
            ->get('/officers/audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('officers/audit')
                ->has('events', 2)
                ->has('offices', 2)
                ->has('designations', 2)
                ->where('events.0.action', 'updated')
                ->where('events.0.actor_name', $admin->name)
                ->where('events.0.old_values.name', 'Test Officer')
                ->where('events.0.old_values.office_id', $firstOffice->id)
                ->where('events.0.old_values.designation_id', $firstDesignation->id)
                ->where('events.0.new_values.name', 'Updated Officer')
                ->where('events.0.new_values.office_id', $secondOffice->id)
                ->where('events.0.new_values.designation_id', $secondDesignation->id)
                ->where('events.0.new_values.is_active', false)
                ->where('events.1.action', 'created')
            );
    }

    private function office(string $code): Office
    {
        return Office::create([
            'code' => $code,
            'name' => 'Office '.$code,
            'type' => 'zonal',
            'is_active' => true,
        ]);
    }

    private function designation(string $code): Designation
    {
        return Designation::create([
            'code' => $code,
            'name' => 'Designation '.$code,
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