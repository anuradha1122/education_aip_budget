<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Services\DesignationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DesignationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_designation_and_normalises_details(): void
    {
        $designation = app(DesignationService::class)->create([
            'code' => ' deo ',
            'name' => ' Director of Education ',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'code' => 'DEO',
            'name' => 'Director of Education',
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_a_duplicate_code_after_normalisation(): void
    {
        Designation::create([
            'code' => 'DEO',
            'name' => 'Director of Education',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(DesignationService::class)->create([
            'code' => ' deo ',
            'name' => 'Another designation',
            'is_active' => true,
        ]);
    }

    public function test_it_requires_a_name(): void
    {
        $this->expectException(ValidationException::class);

        app(DesignationService::class)->create([
            'code' => 'DEO',
            'name' => '   ',
            'is_active' => true,
        ]);
    }

    public function test_it_allows_a_designation_to_keep_its_code_when_updated(): void
    {
        $designation = Designation::create([
            'code' => 'DEO',
            'name' => 'Old name',
            'is_active' => true,
        ]);

        app(DesignationService::class)->update($designation, [
            'code' => ' deo ',
            'name' => 'New name',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'code' => 'DEO',
            'name' => 'New name',
        ]);
    }

    public function test_it_deactivates_a_designation_without_deleting_it(): void
    {
        $designation = Designation::create([
            'code' => 'ADE',
            'name' => 'Assistant Director of Education',
            'is_active' => true,
        ]);

        app(DesignationService::class)->deactivate($designation);

        $this->assertDatabaseHas('designations', [
            'id' => $designation->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseCount('designations', 1);
    }
}