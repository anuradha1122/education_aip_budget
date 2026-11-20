<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserAccessHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_resolves_only_recorded_offices_without_changing_audit_values(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        $first = DB::table('offices')->insertGetId(['code' => 'ZONE-1', 'name' => 'First office', 'type' => 'zonal', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()]);
        $second = DB::table('offices')->insertGetId(['code' => 'ZONE-2', 'name' => 'Second office', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $other = DB::table('offices')->insertGetId(['code' => 'ZONE-3', 'name' => 'Other office', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('user_access_assignment_audit_events')->insertGetId([
            'user_id' => $user->id, 'actor_id' => $admin->id, 'action' => 'updated',
            'old_values' => json_encode(['office_id' => $first]),
            'new_values' => json_encode(['office_id' => $second]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($admin)->get('/user-management/'.$user->id.'/history')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.data.0.old_values.office_id', $first)
                ->where('events.data.0.new_values.office_id', $second)
                ->where('offices.'.$first.'.name', 'First office')
                ->where('offices.'.$second.'.code', 'ZONE-2')
                ->missing('offices.'.$other));
        $this->assertSame($first, json_decode(DB::table('user_access_assignment_audit_events')->where('id', $id)->value('old_values'), true)['office_id']);
    }

    public function test_only_administrators_can_view_a_users_history(): void
    {
        $user = User::factory()->create();
        $url = '/user-management/'.$user->id.'/history';
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($user)->get($url)->assertForbidden();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('user-management/history')->where('subjectUser.id', $user->id)->has('events.data', 0));
    }

    public function test_history_contains_only_selected_users_events_and_excludes_unexpected_fields(): void
    {
        $admin = User::factory()->create(['name' => 'Administrator']);
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$user, $other] as $target) {
            DB::table('user_access_assignment_audit_events')->insert([
                'user_id' => $target->id, 'actor_id' => $admin->id, 'action' => 'details_updated',
                'old_values' => json_encode(['name' => 'Old name', 'password' => 'must-not-appear']),
                'new_values' => json_encode(['name' => 'New name', 'password_changed' => true, 'password' => 'must-not-appear']),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->actingAs($admin)->get('/user-management/'.$user->id.'/history')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('events.data', 1)
                ->where('events.data.0.actor_name', 'Administrator')
                ->where('events.data.0.old_values.name', 'Old name')
                ->where('events.data.0.new_values.password_changed', true)
                ->missing('events.data.0.old_values.password')
                ->missing('events.data.0.new_values.password'));
    }
}
