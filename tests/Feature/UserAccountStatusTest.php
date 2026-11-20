<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_deactivate_and_reactivate_with_audit(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        $this->actingAs($admin)->patch('/user-management/'.$user->id.'/status', ['is_active' => false])->assertRedirect('/user-management');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
        $this->assertDatabaseHas('user_access_assignment_audit_events', ['user_id' => $user->id, 'actor_id' => $admin->id, 'action' => 'deactivated']);
        $this->patch('/user-management/'.$user->id.'/status', ['is_active' => false])->assertRedirect('/user-management');
        $this->assertDatabaseCount('user_access_assignment_audit_events', 1);
        $this->patch('/user-management/'.$user->id.'/status', ['is_active' => true])->assertRedirect('/user-management');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => true]);
        $this->assertDatabaseHas('user_access_assignment_audit_events', ['user_id' => $user->id, 'actor_id' => $admin->id, 'action' => 'activated']);
    }

    public function test_deactivated_users_cannot_sign_in_or_use_an_existing_session(): void
    {
        $user = User::factory()->create(['email' => 'test@example.test', 'password' => Hash::make('secret-password-123')]);
        $user->forceFill(['is_active' => false])->save();
        $this->post('/login', ['email' => 'test@example.test', 'password' => 'secret-password-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user->fresh())->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guests_regular_users_and_admin_targets_are_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        $this->patch('/user-management/'.$user->id.'/status', ['is_active' => false])->assertRedirect(route('login'));
        $this->actingAs($user)->patch('/user-management/'.$user->id.'/status', ['is_active' => false])->assertForbidden();
        $this->actingAs($admin)->patch('/user-management/'.$admin->id.'/status', ['is_active' => false])->assertSessionHasErrors('is_active');
        $this->assertDatabaseCount('user_access_assignment_audit_events', 0);
        $this->assertTrue((bool) $admin->fresh()->is_active);
    }

    public function test_old_session_is_rejected_after_reactivation_and_new_login_works(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create(['email' => 'revocation@example.test', 'password' => Hash::make('secret-password-123')]);

        $this->actingAs($admin)->patch('/user-management/'.$user->id.'/status', ['is_active' => false])->assertRedirect('/user-management');
        $this->patch('/user-management/'.$user->id.'/status', ['is_active' => true])->assertRedirect('/user-management');
        $this->assertSame(1, (int) $user->fresh()->access_version);

        $this->withSession(['account_access_version' => 0])->actingAs($user->fresh())
            ->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post('/login', ['email' => 'revocation@example.test', 'password' => 'secret-password-123'])
            ->assertRedirect()->assertSessionHas('account_access_version', 1);
        $this->get('/dashboard')->assertOk();
    }
}
