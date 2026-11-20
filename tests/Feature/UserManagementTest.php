<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        return $admin;
    }

    private function office(bool $active = true): int
    {
        return DB::table('offices')->insertGetId(['code' => 'Z1', 'name' => 'Zone 1', 'type' => 'zonal', 'is_active' => $active, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_only_existing_administrators_can_open_or_create(): void
    {
        $this->get('/user-management')->assertRedirect(route('login'));
        $this->post('/user-management', [])->assertRedirect(route('login'));
        $user = User::factory()->create();
        $this->actingAs($user)->get('/user-management')->assertForbidden();
        $this->post('/user-management', [])->assertForbidden();
        $this->actingAs($this->admin())->get('/user-management')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('user-management/index'));
    }

    public function test_admin_creates_user_and_audited_assignment_without_admin_privileges(): void
    {
        $admin = $this->admin();
        $office = $this->office();
        $this->actingAs($admin)->post('/user-management', [
            'name' => 'Example User', 'email' => 'new@example.test',
            'password' => 'secret-password-123', 'password_confirmation' => 'secret-password-123',
            'role' => 'zonal_admin', 'office_id' => $office,
        ])->assertRedirect('/user-management')->assertSessionHasNoErrors();
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('secret-password-123', $user->password));
        $this->assertFalse((bool) $user->is_admin);
        $this->assertDatabaseHas('user_access_assignments', ['user_id' => $user->id, 'role' => 'zonal_admin', 'office_id' => $office]);
        $this->assertDatabaseHas('user_access_assignment_audit_events', ['user_id' => $user->id, 'actor_id' => $admin->id, 'action' => 'created']);
        $this->actingAs($user)->get('/user-management')->assertForbidden();
    }

    public function test_invalid_office_rolls_back_account_and_audit(): void
    {
        $admin = $this->admin();
        $office = $this->office(false);
        $this->actingAs($admin)->post('/user-management', [
            'name' => 'Example User', 'email' => 'new@example.test',
            'password' => 'secret-password-123', 'password_confirmation' => 'secret-password-123',
            'role' => 'zonal_admin', 'office_id' => $office,
        ])->assertSessionHasErrors('office_id');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
        $this->assertDatabaseCount('user_access_assignments', 0);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 0);
    }

    public function test_duplicate_email_and_unconfirmed_password_are_rejected(): void
    {
        $admin = $this->admin();
        $existing = User::factory()->create();
        $this->actingAs($admin)->post('/user-management', [
            'name' => 'Example', 'email' => $existing->email,
            'password' => 'secret-password-123', 'password_confirmation' => 'different-password-123',
            'role' => 'provincial_admin',
        ])->assertSessionHasErrors(['email', 'password']);
        $this->assertDatabaseCount('user_access_assignments', 0);
    }
}
