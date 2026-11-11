<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use App\Services\OfficerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfficerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_officer_and_trims_the_name(): void
    {
        $office = $this->office();
        $designation = $this->designation();

        $officer = app(OfficerService::class)->create([
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => ' Test Officer ',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('officers', [
            'id' => $officer->id,
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => 'Test Officer',
            'is_active' => true,
        ]);
    }

    public function test_it_requires_a_name(): void
    {
        $this->expectException(ValidationException::class);

        app(OfficerService::class)->create([
            'office_id' => $this->office()->id,
            'designation_id' => $this->designation()->id,
            'name' => '   ',
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_an_office_that_does_not_exist(): void
    {
        $this->expectException(ValidationException::class);

        app(OfficerService::class)->create([
            'office_id' => 999999,
            'designation_id' => $this->designation()->id,
            'name' => 'Test Officer',
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_a_designation_that_does_not_exist(): void
    {
        $this->expectException(ValidationException::class);

        app(OfficerService::class)->create([
            'office_id' => $this->office()->id,
            'designation_id' => 999999,
            'name' => 'Test Officer',
            'is_active' => true,
        ]);
    }

    public function test_it_updates_an_officer(): void
    {
        $office = $this->office();
        $designation = $this->designation();

        $officer = Officer::create([
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => 'Old name',
            'is_active' => true,
        ]);

        app(OfficerService::class)->update($officer, [
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => ' Updated name ',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('officers', [
            'id' => $officer->id,
            'name' => 'Updated name',
        ]);
    }

    public function test_it_deactivates_an_officer_without_deleting_it(): void
    {
        $officer = Officer::create([
            'office_id' => $this->office()->id,
            'designation_id' => $this->designation()->id,
            'name' => 'Test Officer',
            'is_active' => true,
        ]);

        app(OfficerService::class)->deactivate($officer);

        $this->assertDatabaseHas('officers', [
            'id' => $officer->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseCount('officers', 1);
    }

    private function office(): Office
    {
        return Office::create([
            'code' => 'OFF-01',
            'name' => 'Test Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);
    }

    private function designation(): Designation
    {
        return Designation::create([
            'code' => 'DES-01',
            'name' => 'Test Designation',
            'is_active' => true,
        ]);
    }
}