<?php

namespace Tests\Feature;

use App\Models\ProgrammeType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgrammeTypeDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_programme_type_can_be_saved(): void
    {
        $programmeType = ProgrammeType::create([
            'code' => 'TEST-01',
            'name' => 'Test programme type',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('programme_types', [
            'id' => $programmeType->id,
            'code' => 'TEST-01',
            'name' => 'Test programme type',
            'is_active' => true,
        ]);

        $this->assertTrue($programmeType->is_active);
    }

    public function test_programme_type_codes_must_be_unique(): void
    {
        ProgrammeType::create([
            'code' => 'TEST-01',
            'name' => 'First type',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        ProgrammeType::create([
            'code' => 'TEST-01',
            'name' => 'Another type',
            'is_active' => true,
        ]);
    }

    public function test_a_programme_type_can_be_marked_inactive(): void
    {
        $programmeType = ProgrammeType::create([
            'code' => 'TEST-02',
            'name' => 'Test type',
            'is_active' => true,
        ]);

        $programmeType->update([
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('programme_types', [
            'id' => $programmeType->id,
            'is_active' => false,
        ]);

        $this->assertFalse($programmeType->fresh()->is_active);
    }
}