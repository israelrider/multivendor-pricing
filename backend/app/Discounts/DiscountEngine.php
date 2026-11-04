<?php

namespace App\Discounts;

use App\Pricing\PricedLine;
use App\Pricing\PricingContext;

/**
 * Runs every registered rule on a line and lets the combiner pick the one to apply.
 * The rules come from the "discount.rules" container tag (see DiscountServiceProvider),
 * so adding a rule never changes this class.
 */
final class DiscountEngine
{
    /** @var list<DiscountRuleInterface> */
    private readonly array $rules;

    /**
     * @param  iterable<DiscountRuleInterface>  $rules
     */
    public function __construct(
        iterable $rules,
        private readonly DiscountCombiner $combiner,
    ) {
        $this->rules = is_array($rules) ? array_values($rules) : iterator_to_array($rules, false);
    }

    public function evaluate(PricedLine $line, PricingContext $context): DiscountResult
    {
        $applicable = [];

        foreach ($this->rules as $rule) {
            $discount = $rule->evaluate($line, $context);

            // A 0% discount is "not applicable", so it never shows up as "also eligible".
            if ($discount !== null && $discount->basisPoints > 0) {
                $applicable[] = $discount;
            }
        }

        return new DiscountResult($applicable, $this->combiner->choose($applicable));
    }
}
