<?php

namespace Tests\Feature;

use App\Services\EstimateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OverflowException;
use Tests\TestCase;

class EstimateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_an_estimate_and_calculates_the_total(): void
    {
        $result = app(EstimateService::class)->create([
            'title' => 'පුහුණු වැඩසටහන',
            'circular_paragraph' => "උදාහරණය\nදෙවන පේළිය",
            'items' => [
                ['description' => 'Example rate', 'rate_in_cents' => 75000, 'units' => 2, 'people' => 1],
                ['description' => 'Example fixed amount', 'rate_in_cents' => 1200000, 'units' => 1, 'people' => 1],
            ],
            'total_in_cents' => 1,
        ]);

        $this->assertSame(1350000, $result['total_in_cents']);
        $this->assertDatabaseHas('estimates', [
            'id' => $result['id'], 'title' => 'පුහුණු වැඩසටහන',
            'circular_paragraph' => "උදාහරණය\nදෙවන පේළිය",
        ]);
        $this->assertDatabaseHas('estimate_items', [
            'estimate_id' => $result['id'], 'description' => 'Example rate',
            'rate_in_cents' => 75000, 'units' => 2, 'people' => 1, 'sort_order' => 0,
        ]);
        $this->assertDatabaseCount('estimate_items', 2);
    }

    public function test_invalid_rows_do_not_save_anything(): void
    {
        try {
            app(EstimateService::class)->create([
                'title' => 'Example',
                'items' => [
                    ['description' => 'Valid', 'rate_in_cents' => 100, 'units' => 1, 'people' => 1],
                    ['description' => 'Invalid', 'rate_in_cents' => -1, 'units' => 1, 'people' => 1],
                ],
            ]);
            $this->fail('Validation should have failed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.1.rate_in_cents', $exception->errors());
        }

        $this->assertDatabaseCount('estimates', 0);
        $this->assertDatabaseCount('estimate_items', 0);
    }

    public function test_overflow_does_not_save_anything(): void
    {
        try {
            app(EstimateService::class)->create([
                'title' => 'Example',
                'items' => [['description' => 'Example', 'rate_in_cents' => 100000000000, 'units' => 1000000, 'people' => 1000000]],
            ]);
            $this->fail('Overflow should have been rejected.');
        } catch (OverflowException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        $this->assertDatabaseCount('estimates', 0);
        $this->assertDatabaseCount('estimate_items', 0);
    }

    public function test_estimates_require_at_least_one_item(): void
    {
        try {
            app(EstimateService::class)->create(['title' => 'Example', 'items' => []]);
            $this->fail('Validation should have failed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertDatabaseCount('estimates', 0);
    }
}
