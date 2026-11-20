<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserOfficerLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_link_active_officer_in_the_users_office_and_audit_it(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        $office = $this->office('A');
        $officer = $this->officer($office, 'Officer A');
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'subject_director', 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        $url = '/user-management/'.$user->id.'/officer';
        $this->actingAs($admin)->put($url, ['officer_id' => $officer])->assertRedirect('/user-management')->assertSessionHasNoErrors();
        $this->put($url, ['officer_id' => $officer])->assertRedirect('/user-management');
        $this->assertDatabaseHas('user_officer_links', ['user_id' => $user->id, 'officer_id' => $officer]);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 1);
        $this->get('/user-management/'.$user->id.'/history')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('events.data.0.action', 'officer_linked')->where('events.data.0.new_values.officer_id', $officer));
        $this->actingAs($user)->get('/aip-activities')->assertOk();
    }

    public function test_other_office_inactive_officer_wrong_role_and_non_admin_are_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $user = User::factory()->create();
        $office = $this->office('A');
        $otherOffice = $this->office('B');
        $other = $this->officer($otherOffice, 'Other office');
        $inactive = $this->officer($office, 'Inactive', false);
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'subject_director', 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        $url = '/user-management/'.$user->id.'/officer';
        $this->put($url, ['officer_id' => $other])->assertRedirect(route('login'));
        $this->actingAs($user)->put($url, ['officer_id' => $other])->assertForbidden();
        $this->actingAs($admin)->put($url, ['officer_id' => $other])->assertSessionHasErrors('officer_id');
        $this->put($url, ['officer_id' => $inactive])->assertSessionHasErrors('officer_id');
        DB::table('user_access_assignments')->where('user_id', $user->id)->update(['role' => 'zonal_admin']);
        $this->put($url, ['officer_id' => $inactive])->assertSessionHasErrors('officer_id');
        $this->assertDatabaseCount('user_officer_links', 0);
        $this->assertDatabaseCount('user_access_assignment_audit_events', 0);
    }

    private function office(string $code): int
    {
        return DB::table('offices')->insertGetId(['code' => $code, 'name' => 'Office '.$code, 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function officer(int $office, string $name, bool $active = true): int
    {
        $designation = DB::table('designations')->insertGetId(['code' => 'D'.uniqid(), 'name' => 'Director', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('officers')->insertGetId(['office_id' => $office, 'designation_id' => $designation, 'name' => $name, 'is_active' => $active, 'created_at' => now(), 'updated_at' => now()]);
    }
}
