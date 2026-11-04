<?php

namespace Tests\Fixtures;

use App\Discounts\Discount;
use App\Discounts\DiscountRuleInterface;
use App\Pricing\PricedLine;
use App\Pricing\PricingContext;

/**
 * A rule that exists only in tests, to prove a new rule needs no change to the engine or services.
 */
final class FlashSaleDiscountRule implements DiscountRuleInterface
{
    public function key(): string
    {
        return 'flash_sale';
    }

    public function evaluate(PricedLine $line, PricingContext $context): ?Discount
    {
        return new Discount($this->key(), 'Flash sale: 50%', 5_000);
    }
}
