<?php

namespace Tests\Feature;

use App\Models\FinancialYear;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_financial_year_can_be_saved(): void
    {
        $financialYear = FinancialYear::create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->assertDatabaseHas('financial_years', [
            'id' => $financialYear->id,
            'year' => 2026,
            'is_closed' => false,
        ]);

        $savedYear = $financialYear->fresh();

        $this->assertSame('2026-01-01', $savedYear->start_date->toDateString());
        $this->assertSame('2026-12-31', $savedYear->end_date->toDateString());
        $this->assertFalse($savedYear->is_closed);
    }

    public function test_financial_year_numbers_must_be_unique(): void
    {
        FinancialYear::create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->expectException(QueryException::class);

        FinancialYear::create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
    }
}