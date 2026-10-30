<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfficeAuditTest extends TestCase
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

    public function test_office_creation_is_recorded_with_the_actor(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->post('/offices', [
                'code' => 'ZONE-01',
                'name' => 'Example Office',
                'type' => 'zonal',
                'is_active' => true,
            ])
            ->assertRedirect('/offices');

        $office = Office::where('code', 'ZONE-01')->firstOrFail();

        $event = DB::table('office_audit_events')
            ->where('office_id', $office->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame('created', $event->action);
        $this->assertNull($event->old_values);

        $newValues = json_decode($event->new_values, true);

        $this->assertSame('ZONE-01', $newValues['code']);
        $this->assertTrue($newValues['is_active']);
    }

    public function test_office_update_records_old_and_new_values(): void
    {
        $admin = $this->administrator();

        $office = Office::create([
            'code' => 'ZONE-02',
            'name' => 'Original Name',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put("/offices/{$office->id}", [
                'code' => 'ZONE-02',
                'name' => 'Updated Name',
                'type' => 'zonal',
                'is_active' => false,
            ])
            ->assertRedirect('/offices');

        $event = DB::table('office_audit_events')
            ->where('office_id', $office->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame('updated', $event->action);

        $oldValues = json_decode($event->old_values, true);
        $newValues = json_decode($event->new_values, true);

        $this->assertSame('Original Name', $oldValues['name']);
        $this->assertTrue($oldValues['is_active']);
        $this->assertSame('Updated Name', $newValues['name']);
        $this->assertFalse($newValues['is_active']);
    }

    public function test_an_unchanged_office_does_not_create_an_audit_event(): void
    {
        $admin = $this->administrator();

        $office = Office::create([
            'code' => 'ZONE-03',
            'name' => 'Example Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put("/offices/{$office->id}", [
                'code' => 'ZONE-03',
                'name' => 'Example Office',
                'type' => 'zonal',
                'is_active' => true,
            ])
            ->assertRedirect('/offices');

        $this->assertDatabaseCount('office_audit_events', 0);
    }

    public function test_a_regular_user_cannot_create_an_office_or_audit_event(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/offices', [
                'code' => 'ZONE-04',
                'name' => 'Example Office',
                'type' => 'zonal',
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('offices', 0);
        $this->assertDatabaseCount('office_audit_events', 0);
    }
}
