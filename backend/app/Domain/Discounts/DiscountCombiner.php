<?php

namespace App\Domain\Discounts;

/**
 * The policy that picks which applicable discount is applied to a line.
 */
interface DiscountCombiner
{
    /**
     * @param  list<Discount>  $applicable  in rule registration order
     */
    public function choose(array $applicable): ?Discount;
}
