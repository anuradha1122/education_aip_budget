<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstimateCircularSettingsDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_circular_settings_can_be_saved(): void
    {
        $id = DB::table('estimate_circular_settings')->insertGetId([
            'circular_number' => 'EXAMPLE-01',
            'rule_paragraph' => 'Example circular rule paragraph.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('estimate_circular_settings', [
            'id' => $id,
            'circular_number' => 'EXAMPLE-01',
            'rule_paragraph' => 'Example circular rule paragraph.',
        ]);
    }

    public function test_circular_settings_can_be_updated(): void
    {
        $id = DB::table('estimate_circular_settings')->insertGetId([
            'circular_number' => null,
            'rule_paragraph' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('estimate_circular_settings')
            ->where('id', $id)
            ->update([
                'circular_number' => 'EXAMPLE-02',
                'rule_paragraph' => 'Updated example paragraph.',
                'updated_at' => now(),
            ]);

        $this->assertDatabaseHas('estimate_circular_settings', [
            'id' => $id,
            'circular_number' => 'EXAMPLE-02',
            'rule_paragraph' => 'Updated example paragraph.',
        ]);
    }
}