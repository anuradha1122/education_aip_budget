<?php

namespace Tests\Feature;

use App\Models\Fund;
use App\Services\FundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FundServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_fund_and_normalises_its_details(): void
    {
        $fund = app(FundService::class)->create([
            'code' => ' fund-01 ',
            'name' => ' Test Fund ',
            'is_active' => true,
        ]);

        $this->assertSame('FUND-01', $fund->code);
        $this->assertSame('Test Fund', $fund->name);
        $this->assertTrue($fund->is_active);

        $this->assertDatabaseHas('funds', [
            'code' => 'FUND-01',
            'name' => 'Test Fund',
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_a_duplicate_code_after_normalisation(): void
    {
        Fund::create([
            'code' => 'FUND-01',
            'name' => 'Existing Fund',
            'is_active' => true,
        ]);

        try {
            app(FundService::class)->create([
                'code' => ' fund-01 ',
                'name' => 'Duplicate Fund',
                'is_active' => true,
            ]);

            $this->fail('The duplicate fund code should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('code', $exception->errors());
        }

        $this->assertDatabaseCount('funds', 1);
    }

    public function test_it_rejects_an_empty_name(): void
    {
        try {
            app(FundService::class)->create([
                'code' => 'FUND-01',
                'name' => '   ',
                'is_active' => true,
            ]);

            $this->fail('The empty fund name should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }

        $this->assertDatabaseCount('funds', 0);
    }

    public function test_it_allows_a_fund_to_keep_its_code_when_updated(): void
    {
        $fund = Fund::create([
            'code' => 'FUND-01',
            'name' => 'Original Name',
            'is_active' => true,
        ]);

        $updated = app(FundService::class)->update($fund, [
            'code' => 'fund-01',
            'name' => 'Updated Name',
            'is_active' => true,
        ]);

        $this->assertSame('FUND-01', $updated->code);
        $this->assertSame('Updated Name', $updated->name);
        $this->assertDatabaseCount('funds', 1);
    }

    public function test_it_deactivates_a_fund_without_deleting_it(): void
    {
        $fund = Fund::create([
            'code' => 'FUND-01',
            'name' => 'Test Fund',
            'is_active' => true,
        ]);

        $updated = app(FundService::class)->deactivate($fund);

        $this->assertFalse($updated->is_active);

        $this->assertDatabaseHas('funds', [
            'id' => $fund->id,
            'is_active' => false,
        ]);
    }
}
