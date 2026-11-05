<?php

namespace App\Domain\Discounts\Combiners;

use App\Domain\Discounts\Discount;
use App\Domain\Discounts\DiscountCombiner;

/**
 * The highest percentage wins, with no stacking. On a tie the rule registered first wins,
 * so the applied rule is deterministic.
 */
final class BestDiscountWins implements DiscountCombiner
{
    public function choose(array $applicable): ?Discount
    {
        $best = null;

        foreach ($applicable as $discount) {
            if ($best === null || $discount->basisPoints > $best->basisPoints) {
                $best = $discount;
            }
        }

        return $best;
    }
}
