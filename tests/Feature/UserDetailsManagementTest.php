<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserDetailsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_updates_details_without_recording_password_and_revokes_old_session(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create(['email' => 'old@example.test', 'password' => Hash::make('old-password-123')]);
        $this->actingAs($admin)->put('/user-management/'.$user->id.'/details', [
            'name' => 'Updated User', 'email' => 'new@example.test',
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertRedirect('/user-management')->assertSessionHasNoErrors();
        $user = $user->fresh();
        $this->assertSame('Updated User', $user->name);
        $this->assertSame('new@example.test', $user->email);
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertSame(1, (int) $user->access_version);
        $event = DB::table('user_access_assignment_audit_events')->where('user_id', $user->id)->where('action', 'details_updated')->first();
        $this->assertNotNull($event);
        $this->assertSame((int) $admin->id, (int) $event->actor_id);
        $this->assertStringNotContainsString('new-password-123', $event->new_values);
        $this->assertTrue(json_decode($event->new_values, true)['password_changed']);
        $this->withSession(['account_access_version' => 0])->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        $this->post('/login', ['email' => 'new@example.test', 'password' => 'new-password-123'])->assertRedirect();
        $this->get('/dashboard')->assertOk();
    }

    public function test_name_only_change_preserves_password_and_session_version(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create(['email' => 'user@example.test']);
        $password = $user->password;
        $this->actingAs($admin)->put('/user-management/'.$user->id.'/details', [
            'name' => 'New Name', 'email' => 'user@example.test', 'password' => '', 'password_confirmation' => '',
        ])->assertRedirect('/user-management')->assertSessionHasNoErrors();
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame(0, (int) $user->fresh()->access_version);
    }

    public function test_invalid_or_unauthorized_updates_do_not_modify_accounts(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create(['email' => 'user@example.test']);
        $other = User::factory()->create(['email' => 'other@example.test']);
        $this->put('/user-management/'.$user->id.'/details', [])->assertRedirect(route('login'));
        $this->actingAs($user)->put('/user-management/'.$other->id.'/details', [])->assertForbidden();
        $this->actingAs($admin)->put('/user-management/'.$user->id.'/details', [
            'name' => 'User', 'email' => 'other@example.test', 'password' => 'secret-password-123', 'password_confirmation' => 'wrong-password-123',
        ])->assertSessionHasErrors(['email', 'password']);
        $this->put('/user-management/'.$admin->id.'/details', ['name' => 'Admin', 'email' => $admin->email])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'user@example.test']);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 0);
    }
}
