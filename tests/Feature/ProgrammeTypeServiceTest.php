<?php

namespace Tests\Feature;

use App\Models\ProgrammeType;
use App\Services\ProgrammeTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProgrammeTypeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_programme_type_and_normalises_details(): void
    {
        $programmeType = app(ProgrammeTypeService::class)->create([
            'code' => ' test-01 ',
            'name' => ' Test programme type ',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('programme_types', [
            'id' => $programmeType->id,
            'code' => 'TEST-01',
            'name' => 'Test programme type',
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_a_duplicate_code_after_normalisation(): void
    {
        ProgrammeType::create([
            'code' => 'TEST-01',
            'name' => 'First type',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ProgrammeTypeService::class)->create([
            'code' => ' test-01 ',
            'name' => 'Another type',
            'is_active' => true,
        ]);
    }

    public function test_it_requires_a_name(): void
    {
        $this->expectException(ValidationException::class);

        app(ProgrammeTypeService::class)->create([
            'code' => 'TEST-01',
            'name' => '   ',
            'is_active' => true,
        ]);
    }

    public function test_it_allows_a_type_to_keep_its_code_when_updated(): void
    {
        $programmeType = ProgrammeType::create([
            'code' => 'TEST-01',
            'name' => 'Old name',
            'is_active' => true,
        ]);

        app(ProgrammeTypeService::class)->update($programmeType, [
            'code' => ' test-01 ',
            'name' => 'New name',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('programme_types', [
            'id' => $programmeType->id,
            'code' => 'TEST-01',
            'name' => 'New name',
        ]);
    }

    public function test_it_deactivates_a_type_without_deleting_it(): void
    {
        $programmeType = ProgrammeType::create([
            'code' => 'TEST-02',
            'name' => 'Test type',
            'is_active' => true,
        ]);

        app(ProgrammeTypeService::class)->deactivate($programmeType);

        $this->assertDatabaseHas('programme_types', [
            'id' => $programmeType->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseCount('programme_types', 1);
    }
}