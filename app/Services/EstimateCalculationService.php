<?php

namespace App\Services;

use InvalidArgumentException;
use OverflowException;

class EstimateCalculationService
{
    /** Calculate a line in cents. The rate must already be in cents. */
    public function lineAmount(int $rateInCents, int $units, int $people): int
    {
        if ($rateInCents < 0 || $units < 0 || $people < 0) {
            throw new InvalidArgumentException('Rate, units and people must not be negative.');
        }

        if ($rateInCents === 0 || $units === 0 || $people === 0) {
            return 0;
        }

        return $this->multiply($this->multiply($rateInCents, $units), $people);
    }

    /** Add line amounts in cents. */
    public function total(array $lineAmounts): int
    {
        $total = 0;

        foreach ($lineAmounts as $amount) {
            if (! is_int($amount) || $amount < 0) {
                throw new InvalidArgumentException('Line amounts must be non-negative integer cents.');
            }

            if ($amount > PHP_INT_MAX - $total) {
                throw new OverflowException('The estimate total is too large.');
            }

            $total += $amount;
        }

        return $total;
    }

    private function multiply(int $left, int $right): int
    {
        if ($right !== 0 && $left > intdiv(PHP_INT_MAX, $right)) {
            throw new OverflowException('The estimate line amount is too large.');
        }

        return $left * $right;
    }
}
