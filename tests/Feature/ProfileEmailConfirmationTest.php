<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileEmailConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_change_requires_current_password_without_changing_any_account_data(): void
    {
        $user = User::factory()->create(['name' => 'Before', 'email' => 'old@example.test', 'password' => Hash::make('correct-password')]);
        $token = $user->remember_token;
        foreach ([null, 'incorrect'] as $password) {
            $this->actingAs($user)->patch('/settings/profile', ['name' => 'After', 'email' => 'new@example.test', 'current_password' => $password])->assertSessionHasErrors('current_password');
            $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Before', 'email' => 'old@example.test', 'remember_token' => $token, 'access_version' => 0]);
            $this->assertDatabaseCount('user_access_assignment_audit_events', 0);
        }
        $this->patch('/settings/profile', ['name' => 'After', 'email' => 'new@example.test', 'current_password' => 'correct-password'])->assertSessionHasNoErrors()->assertRedirect('/settings/profile');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'After', 'email' => 'new@example.test', 'access_version' => 1]);
        $event = DB::table('user_access_assignment_audit_events')->where('user_id', $user->id)->first();
        $this->assertNotNull($event);
        $this->assertStringNotContainsString('correct-password', $event->new_values);
    }

    public function test_name_only_change_does_not_require_password(): void
    {
        $user = User::factory()->create(['email' => 'same@example.test']);
        $this->actingAs($user)->patch('/settings/profile', ['name' => 'New Name', 'email' => 'same@example.test'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name', 'access_version' => 0]);
    }
}
