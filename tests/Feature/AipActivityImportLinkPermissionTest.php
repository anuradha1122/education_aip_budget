<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AipActivityImportLinkPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_reports_whether_administrator_can_import(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true, 'can_import_aip' => false])->save();
        $this->actingAs($admin)->get('/aip-activities')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('aip-activities/index')->where('canImportAip', false));

        $admin->forceFill(['can_import_aip' => true])->save();
        $this->actingAs($admin->fresh())->get('/aip-activities')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('aip-activities/index')->where('canImportAip', true));
    }

    public function test_guest_and_regular_user_cannot_access_register(): void
    {
        $this->get('/aip-activities')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/aip-activities')->assertForbidden();
    }
}
