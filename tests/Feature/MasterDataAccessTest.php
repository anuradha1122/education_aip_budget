<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MasterDataAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_is_not_an_administrator_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertFalse((bool) $user->fresh()->is_admin);
    }

    public function test_an_administrator_can_manage_master_data(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        $this->assertTrue(
            Gate::forUser($user)->allows('manage-master-data')
        );
    }

    public function test_a_regular_user_cannot_manage_master_data(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(
            Gate::forUser($user)->allows('manage-master-data')
        );
    }

    public function test_a_guest_cannot_manage_master_data(): void
    {
        $this->assertFalse(
            Gate::allows('manage-master-data')
        );
    }
}
