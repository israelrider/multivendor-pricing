<?php

namespace App\Discounts;

/**
 * The policy that picks which applicable discount is applied to a line.
 * See docs/decisions/0002-discount-combining-best-wins.md.
 */
interface DiscountCombiner
{
    /**
     * @param  list<Discount>  $applicable  in rule registration order
     */
    public function choose(array $applicable): ?Discount;
}
