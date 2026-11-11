<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\User;
use App\Services\DesignationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DesignationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_is_recorded_with_the_actor(): void
    {
        $actor = User::factory()->create();

        $designation = app(DesignationService::class)->create([
            'code' => 'DEO',
            'name' => 'Director of Education',
            'is_active' => true,
        ], $actor);

        $this->assertDatabaseHas('designation_audit_events', [
            'designation_id' => $designation->id,
            'actor_id' => $actor->id,
            'action' => 'created',
            'old_values' => null,
        ]);

        $event = DB::table('designation_audit_events')
            ->where('designation_id', $designation->id)
            ->first();

        $newValues = json_decode($event->new_values, true);

        $this->assertSame('DEO', $newValues['code']);
        $this->assertSame('Director of Education', $newValues['name']);
        $this->assertTrue($newValues['is_active']);
    }

    public function test_update_records_previous_and_new_values(): void
    {
        $actor = User::factory()->create();

        $designation = Designation::create([
            'code' => 'DEO',
            'name' => 'Old name',
            'is_active' => true,
        ]);

        app(DesignationService::class)->update($designation, [
            'code' => 'DEO',
            'name' => 'New name',
            'is_active' => false,
        ], $actor);

        $this->assertDatabaseHas('designation_audit_events', [
            'designation_id' => $designation->id,
            'actor_id' => $actor->id,
            'action' => 'updated',
        ]);

        $event = DB::table('designation_audit_events')
            ->where('designation_id', $designation->id)
            ->first();

        $oldValues = json_decode($event->old_values, true);
        $newValues = json_decode($event->new_values, true);

        $this->assertSame('Old name', $oldValues['name']);
        $this->assertTrue($oldValues['is_active']);
        $this->assertSame('New name', $newValues['name']);
        $this->assertFalse($newValues['is_active']);
    }

    public function test_an_unchanged_designation_does_not_create_an_event(): void
    {
        $designation = Designation::create([
            'code' => 'DEO',
            'name' => 'Director of Education',
            'is_active' => true,
        ]);

        app(DesignationService::class)->update($designation, [
            'code' => ' deo ',
            'name' => ' Director of Education ',
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('designation_audit_events', 0);
    }
}