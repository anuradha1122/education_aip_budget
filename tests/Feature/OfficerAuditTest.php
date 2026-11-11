<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Models\User;
use App\Services\OfficerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfficerAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_is_recorded_with_the_actor(): void
    {
        $actor = User::factory()->create();
        $office = $this->office('OFF-01');
        $designation = $this->designation('DES-01');

        $officer = app(OfficerService::class)->create([
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => 'Test Officer',
            'is_active' => true,
        ], $actor);

        $this->assertDatabaseHas('officer_audit_events', [
            'officer_id' => $officer->id,
            'actor_id' => $actor->id,
            'action' => 'created',
            'old_values' => null,
        ]);

        $event = DB::table('officer_audit_events')
            ->where('officer_id', $officer->id)
            ->first();

        $newValues = json_decode($event->new_values, true);

        $this->assertSame($office->id, $newValues['office_id']);
        $this->assertSame($designation->id, $newValues['designation_id']);
        $this->assertSame('Test Officer', $newValues['name']);
        $this->assertTrue($newValues['is_active']);
    }

    public function test_update_records_previous_and_new_values(): void
    {
        $actor = User::factory()->create();
        $firstOffice = $this->office('OFF-01');
        $secondOffice = $this->office('OFF-02');
        $firstDesignation = $this->designation('DES-01');
        $secondDesignation = $this->designation('DES-02');

        $officer = Officer::create([
            'office_id' => $firstOffice->id,
            'designation_id' => $firstDesignation->id,
            'name' => 'Old name',
            'is_active' => true,
        ]);

        app(OfficerService::class)->update($officer, [
            'office_id' => $secondOffice->id,
            'designation_id' => $secondDesignation->id,
            'name' => 'New name',
            'is_active' => false,
        ], $actor);

        $this->assertDatabaseHas('officer_audit_events', [
            'officer_id' => $officer->id,
            'actor_id' => $actor->id,
            'action' => 'updated',
        ]);

        $event = DB::table('officer_audit_events')
            ->where('officer_id', $officer->id)
            ->first();

        $oldValues = json_decode($event->old_values, true);
        $newValues = json_decode($event->new_values, true);

        $this->assertSame($firstOffice->id, $oldValues['office_id']);
        $this->assertSame($firstDesignation->id, $oldValues['designation_id']);
        $this->assertSame('Old name', $oldValues['name']);
        $this->assertTrue($oldValues['is_active']);

        $this->assertSame($secondOffice->id, $newValues['office_id']);
        $this->assertSame($secondDesignation->id, $newValues['designation_id']);
        $this->assertSame('New name', $newValues['name']);
        $this->assertFalse($newValues['is_active']);
    }

    public function test_an_unchanged_officer_does_not_create_an_event(): void
    {
        $office = $this->office('OFF-01');
        $designation = $this->designation('DES-01');

        $officer = Officer::create([
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => 'Test Officer',
            'is_active' => true,
        ]);

        app(OfficerService::class)->update($officer, [
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => ' Test Officer ',
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('officer_audit_events', 0);
    }

    private function office(string $code): Office
    {
        return Office::create([
            'code' => $code,
            'name' => 'Office '.$code,
            'type' => 'zonal',
            'is_active' => true,
        ]);
    }

    private function designation(string $code): Designation
    {
        return Designation::create([
            'code' => $code,
            'name' => 'Designation '.$code,
            'is_active' => true,
        ]);
    }
}