<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProvincialMasterDataAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_provincial_admin_can_access_master_data_and_users_but_not_aip_import_without_grant(): void
    {
        $user = User::factory()->create();
        DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => 'provincial_admin', 'office_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->get('/offices')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('permissions.manage_organisational_data', true)
            ->where('permissions.manage_master_data', false));
        foreach (['/funds', '/votes', '/subjects', '/designations', '/officers', '/programme-types', '/expense-categories', '/officer-subject-assignments', '/office-audit', '/offices/import', '/offices/export/excel'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->post('/offices', [])->assertSessionHasErrors();
        foreach (['/aip-activities/import', '/estimates/create', '/estimate-circular-settings'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->get('/user-management')->assertOk();
        $this->get('/user-management/access-review')->assertOk();
        $this->post('/user-management', [])->assertSessionHasErrors('name');
        $this->post('/aip-activities/import/save', [])->assertForbidden();
    }

    public function test_zonal_user_and_invalid_provincial_scope_are_denied(): void
    {
        $office = DB::table('offices')->insertGetId(['code' => 'ZONE', 'name' => 'Zone', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['zonal_admin', 'provincial_admin'] as $role) {
            $user = User::factory()->create();
            DB::table('user_access_assignments')->insert(['user_id' => $user->id, 'role' => $role, 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
            $this->actingAs($user)->get('/offices')->assertForbidden();
            $this->post('/offices', [])->assertForbidden();
            $this->get('/offices/export/excel')->assertForbidden();
        }
    }

    public function test_system_admin_retains_access_and_guest_is_redirected(): void
    {
        $this->get('/offices')->assertRedirect(route('login'));
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/offices')->assertOk();
        $this->get('/user-management')->assertOk();
    }

    public function test_master_routes_are_separate_from_sensitive_routes(): void
    {
        foreach (['offices.index', 'offices.store', 'offices.import.validate', 'offices.export.pdf', 'funds.index', 'votes.index', 'subjects.index', 'officers.index', 'designations.index', 'programme-types.index', 'expense-categories.index', 'officer-subject-assignments.index'] as $name) {
            $this->assertContains('can:manage-organisational-data', Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
        foreach (['user-management.index', 'user-management.store'] as $name) {
            $this->assertContains('can:manage-user-accounts', Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
        $this->assertContains('can:manage-master-data', Route::getRoutes()->getByName('estimates.create')->gatherMiddleware());
        $this->assertContains('can:import-aip', Route::getRoutes()->getByName('aip-activities.import.index')->gatherMiddleware());
    }
}
