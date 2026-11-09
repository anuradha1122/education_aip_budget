<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubjectAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_is_recorded_with_the_actor(): void
    {
        $office = $this->office();
        $actor = User::factory()->create();

        $subject = app(SubjectService::class)->create([
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'Test subject',
            'is_active' => true,
        ], $actor);

        $event = DB::table('subject_audit_events')
            ->where('subject_id', $subject->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($actor->id, $event->actor_id);
        $this->assertSame('created', $event->action);
        $this->assertNull($event->old_values);

        $values = json_decode($event->new_values, true);

        $this->assertEquals($office->id, $values['office_id']);
        $this->assertSame('SUB-01', $values['code']);
        $this->assertSame('Test subject', $values['name']);
        $this->assertTrue($values['is_active']);
    }

    public function test_update_records_previous_and_new_values(): void
    {
        $office = $this->office();
        $actor = User::factory()->create();
        $service = app(SubjectService::class);

        $subject = $service->create([
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'Original name',
            'is_active' => true,
        ], $actor);

        $service->update($subject, [
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'Updated name',
            'is_active' => false,
        ], $actor);

        $event = DB::table('subject_audit_events')
            ->where('subject_id', $subject->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($actor->id, $event->actor_id);

        $oldValues = json_decode($event->old_values, true);
        $newValues = json_decode($event->new_values, true);

        $this->assertSame('Original name', $oldValues['name']);
        $this->assertTrue($oldValues['is_active']);
        $this->assertSame('Updated name', $newValues['name']);
        $this->assertFalse($newValues['is_active']);
    }

    public function test_an_unchanged_subject_does_not_create_an_update_event(): void
    {
        $office = $this->office();
        $actor = User::factory()->create();
        $service = app(SubjectService::class);

        $data = [
            'office_id' => $office->id,
            'code' => 'SUB-01',
            'name' => 'Test subject',
            'is_active' => true,
        ];

        $subject = $service->create($data, $actor);
        $service->update($subject, $data, $actor);

        $this->assertDatabaseCount('subject_audit_events', 1);
    }

    private function office(): Office
    {
        return Office::create([
            'code' => 'OFF-01',
            'name' => 'Test office',
            'type' => 'zonal',
            'is_active' => true,
        ]);
    }
}