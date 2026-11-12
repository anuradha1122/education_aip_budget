<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfficerSubjectAssignmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_regular_user_cannot_manage_assignments(): void
    {
        $this->get('/officer-subject-assignments')->assertRedirect(route('login'));
        $this->post('/officer-subject-assignments', [])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get('/officer-subject-assignments')->assertForbidden();
        $this->post('/officer-subject-assignments', [])->assertForbidden();
    }

    public function test_admin_can_assign_and_the_actor_is_audited(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $office = Office::create(['code' => 'OFF-01', 'name' => 'Example office', 'type' => 'zonal', 'is_active' => true]);
        $designation = Designation::create(['code' => 'DES-01', 'name' => 'Example designation', 'is_active' => true]);
        $officer = Officer::create(['office_id' => $office->id, 'designation_id' => $designation->id, 'name' => 'Example officer', 'is_active' => true]);
        $subject = Subject::create(['office_id' => $office->id, 'code' => 'SUB-01', 'name' => 'Example subject', 'is_active' => true]);

        $this->actingAs($admin)->post('/officer-subject-assignments', [
            'officer_id' => $officer->id, 'subject_id' => $subject->id,
        ])->assertRedirect(route('officer-subject-assignments.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('officer_subject_assignment_audit_events', [
            'actor_id' => $admin->id, 'officer_id' => $officer->id, 'subject_id' => $subject->id, 'action' => 'assigned',
        ]);
        $this->get('/officer-subject-assignments')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('officer-subject-assignments/index')->has('assignments.data', 1)
            ->where('assignments.data.0.officer_name', 'Example officer')
            ->where('assignments.data.0.subject_code', 'SUB-01'));
    }
}
