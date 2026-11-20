<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserAccessReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_view_the_review(): void
    {
        $this->get('/user-management/access-review')->assertRedirect(route('login'));
        $regular = User::factory()->create();
        $this->actingAs($regular)->get('/user-management/access-review')->assertForbidden();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/user-management/access-review')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('user-management/access-review'));
    }

    public function test_review_reports_current_permissions_and_hides_credentials(): void
    {
        $admin = User::factory()->create(['name' => 'Review Admin']);
        $admin->forceFill(['is_admin' => true, 'can_import_aip' => true])->save();
        $user = User::factory()->create(['name' => 'Zonal Viewer']);
        $office = DB::table('offices')->insertGetId(['code' => 'REVIEW', 'name' => 'Review office', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'zonal_admin', 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin)->get('/user-management/access-review?search=Zonal')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('search', 'Zonal')->has('users.data', 1)
                ->where('users.data.0.role', 'zonal_admin')->where('users.data.0.office_name', 'Review office')
                ->where('users.data.0.register_allowed', true)->where('users.data.0.import_allowed', false)
                ->missing('users.data.0.password')->missing('users.data.0.remember_token'));
        $this->get('/user-management/access-review?search=Review%20Admin')->assertInertia(fn (Assert $page) => $page
            ->where('users.data.0.register_allowed', true)->where('users.data.0.import_allowed', true));
        DB::table('offices')->where('id', $office)->update(['is_active' => false]);
        $this->get('/user-management/access-review?search=Zonal')->assertInertia(fn (Assert $page) => $page
            ->where('users.data.0.register_allowed', false));
        $admin->forceFill(['is_active' => false])->save();
        // Inspect an inactive account through a second, active administrator.
        $other = User::factory()->create();
        $other->forceFill(['is_admin' => true])->save();
        $this->actingAs($other)->get('/user-management/access-review?search=Review%20Admin')->assertInertia(fn (Assert $page) => $page
            ->where('users.data.0.register_allowed', false)->where('users.data.0.import_allowed', false));
    }
}
