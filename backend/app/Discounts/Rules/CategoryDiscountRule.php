<?php

namespace App\Discounts\Rules;

use App\Discounts\Discount;
use App\Discounts\DiscountRuleInterface;
use App\Pricing\PricedLine;
use App\Pricing\PricingContext;
use Illuminate\Container\Attributes\Config;

/**
 * The product's category has a configured discount. Categories are matched by slug.
 */
final class CategoryDiscountRule implements DiscountRuleInterface
{
    /**
     * @param  array<string, int>  $rates  category slug => basis points
     */
    public function __construct(#[Config('discounts.categories', [])] private readonly array $rates) {}

    public function key(): string
    {
        return 'category';
    }

    public function evaluate(PricedLine $line, PricingContext $context): ?Discount
    {
        $bps = $this->rates[$line->categorySlug] ?? null;

        if ($bps === null) {
            return null;
        }

        return new Discount($this->key(), "{$line->categoryName}: ".Discount::formatPercent($bps), $bps);
    }
}
