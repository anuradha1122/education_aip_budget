<?php

namespace Tests\Unit;

use App\Services\EstimateCalculationService;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

class EstimateCalculationServiceTest extends TestCase
{
    public function test_it_calculates_a_line_in_cents(): void
    {
        $calculator = new EstimateCalculationService();

        // Rs. 750.00 for two days and one person = Rs. 1,500.00.
        $this->assertSame(150000, $calculator->lineAmount(75000, 2, 1));
    }

    public function test_it_preserves_cents_and_adds_line_amounts(): void
    {
        $calculator = new EstimateCalculationService();

        $this->assertSame(301, $calculator->total([
            $calculator->lineAmount(101, 1, 1),
            $calculator->lineAmount(100, 2, 1),
        ]));
    }

    public function test_zero_and_flat_amounts_can_be_calculated(): void
    {
        $calculator = new EstimateCalculationService();

        $this->assertSame(0, $calculator->lineAmount(50000, 0, 1));
        $this->assertSame(1200000, $calculator->lineAmount(1200000, 1, 1));
        $this->assertSame(0, $calculator->total([]));
    }

    public function test_negative_inputs_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new EstimateCalculationService())->lineAmount(100, -1, 1);
    }

    public function test_non_integer_line_amounts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new EstimateCalculationService())->total([1.5]);
    }

    public function test_overflow_is_rejected(): void
    {
        $this->expectException(OverflowException::class);
        (new EstimateCalculationService())->lineAmount(PHP_INT_MAX, 2, 1);
    }

    public function test_total_overflow_is_rejected(): void
    {
        $this->expectException(OverflowException::class);
        (new EstimateCalculationService())->total([PHP_INT_MAX, 1]);
    }
}
