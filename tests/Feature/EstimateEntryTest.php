<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EstimateEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_regular_users_cannot_access_estimate_entry(): void
    {
        $this->get('/estimates/create')->assertRedirect(route('login'));
        $this->post('/estimates', [])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get('/estimates/create')->assertForbidden();
        $this->post('/estimates', [])->assertForbidden();
        $this->get('/estimates/1')->assertForbidden();
    }

    public function test_an_administrator_can_save_and_view_a_calculated_estimate(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        DB::table('estimate_circular_settings')->insert([
            'id' => 1, 'rule_paragraph' => 'Default example', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($admin)->get('/estimates/create')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('estimates/create')
                ->where('defaultCircularParagraph', 'Default example'));
        $response = $this->post('/estimates', [
            'title' => 'Example programme', 'circular_paragraph' => 'Edited example',
            'items' => [['description' => 'Example item', 'rate_in_cents' => 75000, 'units' => 2, 'people' => 1]],
            'total_in_cents' => 1,
        ]);
        $id = DB::table('estimates')->value('id');
        $response->assertRedirect(route('estimates.show', $id))->assertSessionHasNoErrors();
        $this->get('/estimates/'.$id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('estimates/show')->where('totalInCents', '150000')
            ->where('estimate.circular_paragraph', 'Edited example')->has('items', 1));
        $this->assertDatabaseHas('estimate_items', ['estimate_id' => $id, 'rate_in_cents' => 75000]);
    }

    public function test_invalid_estimate_does_not_save(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->post('/estimates', ['title' => '', 'items' => []])
            ->assertSessionHasErrors(['title', 'items']);
        $this->assertDatabaseCount('estimates', 0);
    }

    public function test_overflow_is_reported_without_saving(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->post('/estimates', [
            'title' => 'Example',
            'items' => [['description' => 'Large', 'rate_in_cents' => 100000000000, 'units' => 1000000, 'people' => 1000000]],
        ])->assertSessionHasErrors('items');
        $this->assertDatabaseCount('estimates', 0);
    }
}
