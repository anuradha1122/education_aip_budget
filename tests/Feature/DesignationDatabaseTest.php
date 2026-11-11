<?php

namespace Tests\Feature;

use App\Models\Designation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignationDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_designation_can_be_saved(): void
    {
        $designation = Designation::create([
            'code' => 'DEO',
            'name' => 'Director of Education',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'code' => 'DEO',
            'name' => 'Director of Education',
            'is_active' => true,
        ]);

        $this->assertTrue($designation->is_active);
    }

    public function test_designation_codes_must_be_unique(): void
    {
        Designation::create([
            'code' => 'DEO',
            'name' => 'Director of Education',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        Designation::create([
            'code' => 'DEO',
            'name' => 'Another designation',
            'is_active' => true,
        ]);
    }

    public function test_a_designation_can_be_marked_inactive(): void
    {
        $designation = Designation::create([
            'code' => 'ADE',
            'name' => 'Assistant Director of Education',
            'is_active' => true,
        ]);

        $designation->update([
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'is_active' => false,
        ]);

        $this->assertFalse($designation->fresh()->is_active);
    }
}