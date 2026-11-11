<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\Office;
use App\Models\Officer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficerDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_officer_can_be_saved_with_an_office_and_designation(): void
    {
        $office = $this->office();
        $designation = $this->designation();

        $officer = Officer::create([
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => 'Test Officer',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('officers', [
            'id' => $officer->id,
            'office_id' => $office->id,
            'designation_id' => $designation->id,
            'name' => 'Test Officer',
            'is_active' => true,
        ]);

        $this->assertSame($office->id, $officer->office->id);
        $this->assertSame($designation->id, $officer->designation->id);
        $this->assertTrue($officer->is_active);
    }

    public function test_an_officer_can_be_marked_inactive(): void
    {
        $officer = Officer::create([
            'office_id' => $this->office()->id,
            'designation_id' => $this->designation()->id,
            'name' => 'Test Officer',
            'is_active' => true,
        ]);

        $officer->update([
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('officers', [
            'id' => $officer->id,
            'is_active' => false,
        ]);

        $this->assertFalse($officer->fresh()->is_active);
    }

    public function test_an_officer_requires_an_existing_office(): void
    {
        $designation = $this->designation();

        $this->expectException(QueryException::class);

        Officer::create([
            'office_id' => 999999,
            'designation_id' => $designation->id,
            'name' => 'Test Officer',
            'is_active' => true,
        ]);
    }

    public function test_an_officer_requires_an_existing_designation(): void
    {
        $office = $this->office();

        $this->expectException(QueryException::class);

        Officer::create([
            'office_id' => $office->id,
            'designation_id' => 999999,
            'name' => 'Test Officer',
            'is_active' => true,
        ]);
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