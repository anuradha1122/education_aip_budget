<?php

namespace Tests\Feature;

use App\Models\ProgrammeType;
use App\Models\User;
use App\Services\ProgrammeTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProgrammeTypeAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_is_recorded_with_the_actor(): void
    {
        $actor = User::factory()->create();

        $programmeType = app(ProgrammeTypeService::class)->create([
            'code' => 'TEST-01',
            'name' => 'Test type',
            'is_active' => true,
        ], $actor);

        $this->assertDatabaseHas('programme_type_audit_events', [
            'programme_type_id' => $programmeType->id,
            'actor_id' => $actor->id,
            'action' => 'created',
            'old_values' => null,
        ]);

        $event = DB::table('programme_type_audit_events')
            ->where('programme_type_id', $programmeType->id)
            ->first();

        $newValues = json_decode($event->new_values, true);

        $this->assertSame('TEST-01', $newValues['code']);
        $this->assertSame('Test type', $newValues['name']);
        $this->assertTrue($newValues['is_active']);
    }

    public function test_update_records_previous_and_new_values(): void
    {
        $actor = User::factory()->create();

        $programmeType = ProgrammeType::create([
            'code' => 'TEST-01',
            'name' => 'Old name',
            'is_active' => true,
        ]);

        app(ProgrammeTypeService::class)->update($programmeType, [
            'code' => 'TEST-01',
            'name' => 'New name',
            'is_active' => false,
        ], $actor);

        $this->assertDatabaseHas('programme_type_audit_events', [
            'programme_type_id' => $programmeType->id,
            'actor_id' => $actor->id,
            'action' => 'updated',
        ]);

        $event = DB::table('programme_type_audit_events')
            ->where('programme_type_id', $programmeType->id)
            ->first();

        $oldValues = json_decode($event->old_values, true);
        $newValues = json_decode($event->new_values, true);

        $this->assertSame('Old name', $oldValues['name']);
        $this->assertTrue($oldValues['is_active']);
        $this->assertSame('New name', $newValues['name']);
        $this->assertFalse($newValues['is_active']);
    }

    public function test_an_unchanged_type_does_not_create_an_event(): void
    {
        $programmeType = ProgrammeType::create([
            'code' => 'TEST-01',
            'name' => 'Test type',
            'is_active' => true,
        ]);

        app(ProgrammeTypeService::class)->update($programmeType, [
            'code' => ' test-01 ',
            'name' => ' Test type ',
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('programme_type_audit_events', 0);
    }
}