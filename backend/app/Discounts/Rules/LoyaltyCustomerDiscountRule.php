<?php

namespace App\Discounts\Rules;

use App\Discounts\Discount;
use App\Discounts\DiscountRuleInterface;
use App\Pricing\PricedLine;
use App\Pricing\PricingContext;
use Illuminate\Container\Attributes\Config;

/**
 * The customer is flagged as loyal. It's a customer-level fact, but it is evaluated per line like
 * every rule, so the discount lands in the right vendor sub-order (ADR 0002, section 2).
 */
final class LoyaltyCustomerDiscountRule implements DiscountRuleInterface
{
    public function __construct(#[Config('discounts.loyalty_bps', 0)] private readonly int $basisPoints) {}

    public function key(): string
    {
        return 'loyalty';
    }

    public function evaluate(PricedLine $line, PricingContext $context): ?Discount
    {
        if (! $context->customerIsLoyal || $this->basisPoints === 0) {
            return null;
        }

        return new Discount($this->key(), 'Loyal customer: '.Discount::formatPercent($this->basisPoints), $this->basisPoints);
    }
}
