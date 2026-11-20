<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ProfileEmailRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_password_attempts_temporarily_limit_email_changes_per_user(): void
    {
        $user = User::factory()->create(['name' => 'Before', 'email' => 'old@example.test']);
        $this->actingAs($user);
        $payload = ['name' => 'Before', 'email' => 'new@example.test', 'current_password' => 'wrong'];
        for ($i = 0; $i < 5; $i++) {
            $this->patch('/settings/profile', $payload)->assertSessionHasErrors('current_password');
        }
        $payload['current_password'] = 'password';
        $this->patch('/settings/profile', $payload)->assertSessionHasErrors('current_password');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'old@example.test']);
        $this->patch('/settings/profile', ['name' => 'After', 'email' => 'old@example.test'])->assertSessionHasNoErrors();
        $other = User::factory()->create(['email' => 'other@example.test']);
        $this->actingAs($other)->patch('/settings/profile', ['name' => $other->name, 'email' => 'other-new@example.test', 'current_password' => 'password'])->assertSessionHasNoErrors();
        $this->withSession(['account_access_version' => 0])->actingAs($user->fresh());
        $this->travel(61)->seconds();
        $this->patch('/settings/profile', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@example.test']);
        $this->assertFalse(RateLimiter::tooManyAttempts('profile-email-password:'.$user->id, 1));
    }
}
