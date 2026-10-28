<?php

namespace Tests\Feature;

use App\Models\Office;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfficeDatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use a fresh, isolated database for these tests.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);

        DB::purge('sqlite');

        Artisan::call('migrate', ['--force' => true]);
    }

    public function test_an_office_can_be_saved(): void
    {
        $office = Office::create([
            'code' => 'TEST-PROV',
            'name' => 'Test Provincial Office',
            'type' => 'provincial',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'code' => 'TEST-PROV',
            'name' => 'Test Provincial Office',
        ]);

        $this->assertTrue($office->fresh()->is_active);
    }

    public function test_office_codes_must_be_unique(): void
    {
        Office::create([
            'code' => 'TEST-01',
            'name' => 'First Test Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        Office::create([
            'code' => 'TEST-01',
            'name' => 'Second Test Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);
    }

    public function test_an_office_can_be_marked_inactive(): void
    {
        $office = Office::create([
            'code' => 'TEST-02',
            'name' => 'Test Zonal Office',
            'type' => 'zonal',
            'is_active' => true,
        ]);

        $office->update(['is_active' => false]);

        $this->assertFalse($office->fresh()->is_active);
    }
}
