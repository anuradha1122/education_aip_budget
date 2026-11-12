<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EstimateCircularSettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_access_or_update_circular_settings(): void
    {
        $this->get('/estimate-circular-settings')->assertRedirect(route('login'));
        $this->put('/estimate-circular-settings', [])->assertRedirect(route('login'));
        $this->assertDatabaseCount('estimate_circular_settings', 0);
    }

    public function test_a_regular_user_cannot_access_or_update_circular_settings(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/estimate-circular-settings')->assertForbidden();
        $this->put('/estimate-circular-settings', [])->assertForbidden();
        $this->assertDatabaseCount('estimate_circular_settings', 0);
    }

    public function test_an_administrator_can_save_and_update_the_common_settings(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);

        $this->get('/estimate-circular-settings')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('estimate-circular-settings/index')
                ->where('settings.circular_number', '')
                ->where('settings.rule_paragraph', ''));

        $this->put('/estimate-circular-settings', [
            'circular_number' => 'EXAMPLE-01',
            'rule_paragraph' => 'Example rule.',
        ])->assertRedirect(route('estimate-circular-settings.index'))->assertSessionHasNoErrors();

        $this->put('/estimate-circular-settings', [
            'circular_number' => 'EXAMPLE-02',
            'rule_paragraph' => 'Updated example rule.',
        ])->assertRedirect(route('estimate-circular-settings.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('estimate_circular_settings', 1);
        $this->assertDatabaseHas('estimate_circular_settings', [
            'id' => 1,
            'circular_number' => 'EXAMPLE-02',
            'rule_paragraph' => 'Updated example rule.',
        ]);

        $this->get('/estimate-circular-settings')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('estimate-circular-settings/index')
                ->where('settings.circular_number', 'EXAMPLE-02')
                ->where('settings.rule_paragraph', 'Updated example rule.'));
    }

    public function test_long_circular_numbers_are_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->put('/estimate-circular-settings', [
            'circular_number' => str_repeat('A', 256),
            'rule_paragraph' => 'Example rule.',
        ])->assertSessionHasErrors('circular_number');
        $this->assertDatabaseCount('estimate_circular_settings', 0);
    }
}
