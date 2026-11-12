<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstimateDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_estimate_and_its_items_can_be_saved(): void
    {
        $id = DB::table('estimates')->insertGetId([
            'title' => 'පුහුණු වැඩසටහන',
            'circular_paragraph' => "පළමු පේළිය\nදෙවන පේළිය",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('estimate_items')->insert([
            'estimate_id' => $id,
            'description' => 'Example item',
            'rate_in_cents' => 75000,
            'units' => 2,
            'people' => 1,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('estimates', [
            'id' => $id,
            'title' => 'පුහුණු වැඩසටහන',
            'circular_paragraph' => "පළමු පේළිය\nදෙවන පේළිය",
        ]);
        $this->assertDatabaseHas('estimate_items', [
            'estimate_id' => $id,
            'rate_in_cents' => 75000,
            'units' => 2,
            'people' => 1,
        ]);
    }

    public function test_an_item_requires_an_existing_estimate(): void
    {
        $this->expectException(QueryException::class);

        DB::table('estimate_items')->insert([
            'estimate_id' => 999999,
            'description' => 'Example item',
            'rate_in_cents' => 10000,
            'units' => 1,
            'people' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
