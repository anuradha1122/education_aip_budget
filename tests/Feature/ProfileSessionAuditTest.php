<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileSessionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_change_audits_values_and_revokes_other_sessions(): void
    {
        $user = User::factory()->create(['name' => 'Previous Name', 'email' => 'old@example.test', 'password' => Hash::make('secret-password-123')]);
        $this->actingAs($user)->patch('/settings/profile', ['name' => 'New Name', 'email' => 'new@example.test', 'current_password' => 'secret-password-123'])
            ->assertRedirect('/settings/profile')->assertSessionHasNoErrors()->assertSessionHas('account_access_version', 1);
        $this->assertSame(1, (int) $user->fresh()->access_version);
        $this->assertNull($user->fresh()->email_verified_at);
        $event = DB::table('user_access_assignment_audit_events')->where('user_id', $user->id)->where('action', 'profile_updated')->first();
        $this->assertNotNull($event);
        $this->assertSame((int) $user->id, (int) $event->actor_id);
        $this->assertSame('old@example.test', json_decode($event->old_values, true)['email']);
        $this->assertSame('new@example.test', json_decode($event->new_values, true)['email']);
        $this->get('/dashboard')->assertOk();
        $this->withSession(['account_access_version' => 0])->actingAs($user->fresh())->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post('/login', ['email' => 'new@example.test', 'password' => 'secret-password-123'])->assertRedirect();
        $this->get('/dashboard')->assertOk();
    }

    public function test_name_only_is_audited_without_revoking_sessions_and_noop_has_no_event(): void
    {
        $user = User::factory()->create(['name' => 'Before', 'email' => 'person@example.test']);
        $this->actingAs($user)->patch('/settings/profile', ['name' => 'After', 'email' => 'person@example.test'])->assertRedirect('/settings/profile');
        $this->assertSame(0, (int) $user->fresh()->access_version);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 1);
        $this->patch('/settings/profile', ['name' => 'After', 'email' => 'person@example.test'])->assertRedirect('/settings/profile');
        $this->assertDatabaseCount('user_access_assignment_audit_events', 1);
    }

    public function test_duplicate_email_and_guest_are_rejected_without_audit(): void
    {
        $user = User::factory()->create(['email' => 'first@example.test']);
        User::factory()->create(['email' => 'taken@example.test']);
        $this->patch('/settings/profile', [])->assertRedirect(route('login'));
        $this->actingAs($user)->patch('/settings/profile', ['name' => 'Changed', 'email' => 'taken@example.test'])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'first@example.test']);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 0);
    }
}
