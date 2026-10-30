<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'is_admin' => true,
        ])->save();

        return $user;
    }

    public function test_a_guest_cannot_access_office_management(): void
    {
        $this->get('/offices')->assertRedirect('/login');
    }

    public function test_a_regular_user_cannot_access_office_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/offices')
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/offices', [
                'code' => 'ZONE-01',
                'name' => 'Example Office',
                'type' => 'zonal',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('offices', [
            'code' => 'ZONE-01',
        ]);
    }

    public function test_an_administrator_can_view_the_office_page(): void
    {
        $this->actingAs($this->administrator())
            ->get('/offices')
            ->assertOk();
    }

    public function test_an_administrator_can_create_an_office(): void
    {
        $this->actingAs($this->administrator())
            ->post('/offices', [
                'code' => ' zone-01 ',
                'name' => 'Example Zonal Office',
                'type' => 'zonal',
                'is_active' => true,
            ])
            ->assertRedirect('/offices');

        $this->assertDatabaseHas('offices', [
            'code' => 'ZONE-01',
            'name' => 'Example Zonal Office',
        ]);
    }

    public function test_duplicate_office_codes_are_rejected(): void
    {
        Office::create([
            'code' => 'ZONE-01',
            'name' => 'Existing Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $this->actingAs($this->administrator())
            ->from('/offices')
            ->post('/offices', [
                'code' => 'zone-01',
                'name' => 'Another Office',
                'type' => 'zonal',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Office::count());
    }

    public function test_an_administrator_can_deactivate_an_office(): void
    {
        $office = Office::create([
            'code' => 'ZONE-02',
            'name' => 'Example Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $this->actingAs($this->administrator())
            ->put("/offices/{$office->id}", [
                'code' => 'ZONE-02',
                'name' => 'Example Office',
                'type' => 'zonal',
                'is_active' => false,
            ])
            ->assertRedirect('/offices');

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'is_active' => false,
        ]);
    }

    public function test_a_regular_user_cannot_edit_an_office(): void
    {
        $office = Office::create([
            'code' => 'ZONE-03',
            'name' => 'Original Name',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get("/offices/{$office->id}/edit")
            ->assertForbidden();

        $this->actingAs($user)
            ->put("/offices/{$office->id}", [
                'code' => 'ZONE-03',
                'name' => 'Changed Name',
                'type' => 'zonal',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertSame('Original Name', $office->fresh()->name);
    }
}
