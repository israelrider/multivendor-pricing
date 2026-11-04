<?php

namespace App\Discounts;

use InvalidArgumentException;

/**
 * One applicable discount: a percentage in basis points (1000 bps = 10%) plus the rule that gave it.
 * It holds no amounts. PricingService works out the cents per line.
 */
final readonly class Discount
{
    public const int MAX_BPS = 10_000;

    public function __construct(
        public string $rule,
        public string $label,
        public int $basisPoints,
    ) {
        if ($basisPoints < 0 || $basisPoints > self::MAX_BPS) {
            throw new InvalidArgumentException("Discount basis points must be 0..10000, got {$basisPoints}.");
        }
    }

    /** 1000 => "10%", 250 => "2.5%", 25 => "0.25%". */
    public static function formatPercent(int $basisPoints): string
    {
        return rtrim(rtrim(number_format($basisPoints / 100, 2, '.', ''), '0'), '.').'%';
    }
}
