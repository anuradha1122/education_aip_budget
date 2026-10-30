<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfficeNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_receives_master_data_permission(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('permissions.manage_master_data', true)
            );
    }

    public function test_regular_user_does_not_receive_master_data_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('permissions.manage_master_data', false)
            );
    }

    public function test_guest_does_not_receive_master_data_permission(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('permissions.manage_master_data', false)
            );
    }
}
