<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AipActivityLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_and_physical_location_are_saved_separately(): void
    {
        $yearId = DB::table('financial_years')->insertGetId([
            'year' => 2027,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_closed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)
            ->post('/aip-activities', [
                'financial_year_id' => $yearId,
                'activity_number' => '123',
                'description' => 'Science seminar',
                'level' => 'Zonal',
                'location' => 'Dehiowita Education Office',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('aip_activities', [
            'financial_year_id' => $yearId,
            'activity_number' => '123',
            'level' => 'Zonal',
            'location' => 'Dehiowita Education Office',
        ]);

        $event = DB::table('aip_activity_audit_events')
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($event);

        $values = json_decode(
            $event->new_values,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('Zonal', $values['level']);
        $this->assertSame('Dehiowita Education Office', $values['location']);

        $this->get('/aip-activities?search=123')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('aip-activities/index')
                ->where('activities.data.0.level', 'Zonal')
                ->where(
                    'activities.data.0.location',
                    'Dehiowita Education Office'
                ));
    }

    public function test_activity_number_must_contain_only_digits(): void
    {
        $yearId = DB::table('financial_years')->insertGetId([
            'year' => 2027,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_closed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)
            ->post('/aip-activities', [
                'financial_year_id' => $yearId,
                'activity_number' => 'x1',
                'description' => 'Example activity',
            ])
            ->assertSessionHasErrors('activity_number');

        $this->assertDatabaseCount('aip_activities', 0);
        $this->assertDatabaseCount('aip_activity_audit_events', 0);

        $this->post('/aip-activities', [
            'financial_year_id' => $yearId,
            'activity_number' => '001',
            'description' => 'Example activity',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('aip_activities', [
            'financial_year_id' => $yearId,
            'activity_number' => '001',
        ]);
    }
}