<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Services\OfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfficeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_office_and_normalises_the_details(): void
    {
        $office = app(OfficeService::class)->create([
            'code' => '  zone-01  ',
            'name' => '  Example Zonal Office  ',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $this->assertSame('ZONE-01', $office->code);
        $this->assertSame('Example Zonal Office', $office->name);
        $this->assertTrue($office->is_active);
    }

    public function test_it_rejects_a_duplicate_code_after_normalisation(): void
    {
        Office::create([
            'code' => 'ZONE-01',
            'name' => 'First Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        try {
            app(OfficeService::class)->create([
                'code' => ' zone-01 ',
                'name' => 'Second Office',
                'type' => 'zonal',
                'is_active' => true,
            ]);

            $this->fail('A duplicate office code should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('code', $exception->errors());
        }
    }

    public function test_it_rejects_an_invalid_office_type(): void
    {
        try {
            app(OfficeService::class)->create([
                'code' => 'OFFICE-01',
                'name' => 'Example Office',
                'type' => 'district',
                'is_active' => true,
            ]);

            $this->fail('An invalid office type should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
        }
    }

    public function test_it_allows_an_office_to_keep_its_code_when_updated(): void
    {
        $office = Office::create([
            'code' => 'ZONE-01',
            'name' => 'Original Name',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $updated = app(OfficeService::class)->update($office, [
            'code' => 'zone-01',
            'name' => 'Updated Name',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $this->assertSame('ZONE-01', $updated->code);
        $this->assertSame('Updated Name', $updated->name);
    }

    public function test_it_deactivates_an_office_without_deleting_it(): void
    {
        $office = Office::create([
            'code' => 'ZONE-02',
            'name' => 'Example Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        app(OfficeService::class)->update($office, [
            'code' => 'ZONE-02',
            'name' => 'Example Office',
            'type' => 'zonal',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'code' => 'ZONE-02',
            'is_active' => false,
        ]);

        $this->assertFalse($office->fresh()->is_active);
    }
}
