<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CredentialSessionRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_service_password_change_keeps_current_session_and_revokes_older_sessions(): void
    {
        $user = User::factory()->create(['email' => 'person@example.test', 'password' => Hash::make('old-password-123')]);
        $this->actingAs($user)->from('/settings/password')->put('/settings/password', [
            'current_password' => 'old-password-123', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertRedirect('/settings/password')->assertSessionHasNoErrors()->assertSessionHas('account_access_version', 1);
        $this->assertSame(1, (int) $user->fresh()->access_version);
        $this->get('/dashboard')->assertOk();
        $event = DB::table('user_access_assignment_audit_events')->where('user_id', $user->id)->where('action', 'password_self_changed')->first();
        $this->assertNotNull($event);
        $this->assertSame((int) $user->id, (int) $event->actor_id);
        $this->assertStringNotContainsString('new-password-123', $event->new_values);
        $this->withSession(['account_access_version' => 0])->actingAs($user->fresh())->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_reset_by_token_revokes_old_sessions_and_allows_new_password(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.test', 'password' => Hash::make('old-password-123')]);
        $token = Password::createToken($user);
        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'));
        $this->assertSame(1, (int) $user->fresh()->access_version);
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertDatabaseHas('user_access_assignment_audit_events', ['user_id' => $user->id, 'action' => 'password_reset']);
        $this->withSession(['account_access_version' => 0])->actingAs($user->fresh())->get('/dashboard')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'new-password-123'])->assertRedirect();
        $this->get('/dashboard')->assertOk();
    }

    public function test_incorrect_current_password_does_not_change_version_or_create_audit(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password-123')]);
        $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'incorrect', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('current_password');
        $this->assertSame(0, (int) $user->fresh()->access_version);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 0);
    }
}
