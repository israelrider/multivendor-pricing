<?php

namespace App\Domain\Discounts;

use App\Domain\Pricing\PricedLine;
use App\Domain\Pricing\PricingContext;

/**
 * A discount rule. Rules are listed in config/discounts.php, tagged by DiscountServiceProvider
 * and handed to DiscountEngine, which never names a concrete rule.
 */
interface DiscountRuleInterface
{
    /** Stable key stored on order items and returned by the API, e.g. "quantity". */
    public function key(): string;

    /** The discount this rule gives the line, or null when it does not apply. */
    public function evaluate(PricedLine $line, PricingContext $context): ?Discount;
}
