<?php

namespace App\Discounts\Rules;

use App\Discounts\Discount;
use App\Discounts\DiscountRuleInterface;
use App\Pricing\PricedLine;
use App\Pricing\PricingContext;
use Illuminate\Container\Attributes\Config;
use InvalidArgumentException;

/**
 * Line quantity at or above a threshold. When several tiers match, the highest threshold wins.
 */
final class QuantityThresholdDiscountRule implements DiscountRuleInterface
{
    /** @var array<int, int> min quantity => bps, highest threshold first */
    private readonly array $tiers;

    /**
     * @param  array<int, int>  $tiers  min quantity => basis points, e.g. [5 => 500, 10 => 1000]
     */
    public function __construct(#[Config('discounts.quantity_tiers', [])] array $tiers)
    {
        foreach ($tiers as $minQuantity => $bps) {
            if (! is_int($minQuantity) || $minQuantity < 1 || ! is_int($bps)) {
                throw new InvalidArgumentException('Quantity tiers must map a quantity of at least 1 to integer basis points.');
            }
        }

        krsort($tiers);
        $this->tiers = $tiers;
    }

    public function key(): string
    {
        return 'quantity';
    }

    public function evaluate(PricedLine $line, PricingContext $context): ?Discount
    {
        foreach ($this->tiers as $minQuantity => $bps) {
            if ($line->quantity >= $minQuantity) {
                return new Discount($this->key(), "{$minQuantity}+ units: ".Discount::formatPercent($bps), $bps);
            }
        }

        return null;
    }
}
