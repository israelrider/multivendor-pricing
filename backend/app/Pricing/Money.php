<?php

namespace App\Pricing;

use InvalidArgumentException;

/**
 * Money arithmetic in integer cents. There are no floats anywhere in pricing.
 */
final class Money
{
    /**
     * $basisPoints of $cents, rounded half-up, in integers only.
     * percentOf(49990, 1000) = 4999; percentOf(5, 1000) = 1 (0.5 rounds up); percentOf(4, 1000) = 0.
     */
    public static function percentOf(int $cents, int $basisPoints): int
    {
        if ($cents < 0 || $basisPoints < 0) {
            throw new InvalidArgumentException('Cents and basis points must not be negative.');
        }

        return intdiv($cents * $basisPoints + 5_000, 10_000);
    }
}
