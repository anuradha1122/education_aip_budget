<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetLinkRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_password_change_invalidates_existing_reset_link(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create(['email' => 'reset-check@example.test', 'password' => Hash::make('old-password-123')]);
        $token = Password::broker()->createToken($user);
        $this->actingAs($admin)->put('/user-management/'.$user->id.'/details', [
            'name' => $user->name, 'email' => $user->email,
            'password' => 'admin-password-123', 'password_confirmation' => 'admin-password-123',
        ])->assertSessionHasNoErrors();
        $this->assertFalse(Password::broker()->tokenExists($user->fresh(), $token));
        $this->post('/logout');
        $this->post('/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'attacker-password-123', 'password_confirmation' => 'attacker-password-123',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('admin-password-123', $user->fresh()->password));
    }

    public function test_self_service_password_change_invalidates_existing_reset_link(): void
    {
        $user = User::factory()->create(['email' => 'self-check@example.test', 'password' => Hash::make('old-password-123')]);
        $token = Password::broker()->createToken($user);
        $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();
        $this->assertFalse(Password::broker()->tokenExists($user->fresh(), $token));
        $this->post('/logout');
        $this->post('/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'attacker-password-123', 'password_confirmation' => 'attacker-password-123',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_rejected_password_change_does_not_invalidate_link(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password-123')]);
        $token = Password::broker()->createToken($user);
        $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'incorrect-password',
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Password::broker()->tokenExists($user->fresh(), $token));
    }
}
