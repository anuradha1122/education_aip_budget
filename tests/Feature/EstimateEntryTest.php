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

    private function assignment(): int
    {
        $year = DB::table('financial_years')->insertGetId(['year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'is_closed' => false, 'created_at' => now(), 'updated_at' => now()]);
        $office = DB::table('offices')->insertGetId(['code' => 'ZEO-RAT', 'name' => 'Rathnapura', 'type' => 'zonal', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $activity = DB::table('aip_activities')->insertGetId(['financial_year_id' => $year, 'activity_number' => '1', 'description' => 'Example', 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('aip_activity_offices')->insertGetId(['aip_activity_id' => $activity, 'office_id' => $office, 'created_at' => now(), 'updated_at' => now()]);
    }


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
            'aip_activity_office_id' => $this->assignment(),
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
            'aip_activity_office_id' => $this->assignment(),
            'title' => 'Example',
            'items' => [['description' => 'Large', 'rate_in_cents' => 100000000000, 'units' => 1000000, 'people' => 1000000]],
        ])->assertSessionHasErrors('items');
        $this->assertDatabaseCount('estimates', 0);
    }
}
