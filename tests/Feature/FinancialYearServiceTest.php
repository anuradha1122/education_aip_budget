<?php

namespace Tests\Feature;

use App\Services\FinancialYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialYearServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_open_financial_year(): void
    {
        $financialYear = app(FinancialYearService::class)->create([
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => true,
        ]);

        $this->assertDatabaseHas('financial_years', [
            'id' => $financialYear->id,
            'year' => 2026,
            'is_closed' => false,
        ]);

        $this->assertSame(
            '2026-01-01',
            $financialYear->start_date->toDateString()
        );

        $this->assertSame(
            '2026-12-31',
            $financialYear->end_date->toDateString()
        );
    }

    public function test_it_rejects_a_duplicate_year(): void
    {
        $service = app(FinancialYearService::class);

        $data = [
            'year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ];

        $service->create($data);

        $this->expectException(ValidationException::class);

        $service->create($data);
    }

    public function test_it_rejects_an_end_date_before_the_start_date(): void
    {
        $this->expectException(ValidationException::class);

        app(FinancialYearService::class)->create([
            'year' => 2026,
            'start_date' => '2026-12-31',
            'end_date' => '2026-01-01',
        ]);
    }

    public function test_it_rejects_an_invalid_date(): void
    {
        $this->expectException(ValidationException::class);

        app(FinancialYearService::class)->create([
            'year' => 2026,
            'start_date' => 'not-a-date',
            'end_date' => '2026-12-31',
        ]);
    }

    public function test_it_requires_a_year(): void
    {
        $this->expectException(ValidationException::class);

        app(FinancialYearService::class)->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
    }
}