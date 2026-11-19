<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AipImportPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_permission_defaults_to_denied_even_for_an_administrator(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->assertFalse((bool) $user->fresh()->can_import_aip);
        $this->assertFalse(Gate::forUser($user->fresh())->allows('import-aip'));
        $this->assertTrue(Gate::forUser($user->fresh())->allows('manage-master-data'));
    }

    public function test_only_an_administrator_with_the_import_flag_is_allowed(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true, 'can_import_aip' => true])->save();
        $this->assertTrue(Gate::forUser($user->fresh())->allows('import-aip'));
        $user->forceFill(['is_admin' => false])->save();
        $this->assertFalse(Gate::forUser($user->fresh())->allows('import-aip'));
    }
}
