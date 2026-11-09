<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_access_subjects(): void
    {
        $this->get('/subjects')
            ->assertRedirect(route('login'));
    }

    public function test_a_regular_user_cannot_manage_subjects(): void
    {
        $user = User::factory()->create();
        $office = $this->office();

        $this->actingAs($user)
            ->get('/subjects')
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/subjects', [
                'office_id' => $office->id,
                'code' => 'SUB-01',
                'name' => 'Test subject',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('subjects', 0);
        $this->assertDatabaseCount('subject_audit_events', 0);
    }

    public function test_an_administrator_can_view_subjects(): void
    {
        $this->actingAs($this->administrator())
            ->get('/subjects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('subjects/index')
                ->has('subjects')
                ->has('offices')
            );
    }

    public function test_an_administrator_can_create_a_subject_with_an_audit_event(): void
    {
        $admin = $this->administrator();
        $office = $this->office();

        $this->actingAs($admin)
            ->post('/subjects', [
                'office_id' => $office->id,
                'code' => ' sub-01 ',
                'name' => 'Test subject',
                'is_active' => true,
            ])
            ->assertRedirect(route('subjects.index'))
            ->assertSessionHasNoErrors();

        $subject = Subject::query()
            ->where('office_id', $office->id)
            ->where('code', 'SUB-01')
            ->firstOrFail();

        $this->assertDatabaseHas('subject_audit_events', [
            'subject_id' => $subject->id,
            'actor_id' => $admin->id,
            'action' => 'created',
        ]);
    }

    public function test_an_administrator_can_deactivate_a_subject(): void
    {
        $admin = $this->administrator();
        $office = $this->office();

        $subject = Subject::create([
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'Test subject',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put("/subjects/{$subject->id}", [
                'office_id' => $office->id,
                'code' => 'SUB-01',
                'name' => 'Test subject',
                'is_active' => false,
            ])
            ->assertRedirect(route('subjects.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('subject_audit_events', [
            'subject_id' => $subject->id,
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

    private function office(): Office
    {
        return Office::create([
            'code' => 'OFF-01',
            'name' => 'Test office',
            'type' => 'zonal',
            'is_active' => true,
        ]);
    }
}