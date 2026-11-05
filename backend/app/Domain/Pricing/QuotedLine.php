<?php

namespace App\Domain\Pricing;

use App\Domain\Discounts\Discount;

/**
 * A fully priced line: the offer, every applicable discount, the applied one and the resulting cents.
 * This is what becomes an order_items row.
 */
final readonly class QuotedLine
{
    public int $discountCents;

    public int $totalCents;

    /**
     * @param  list<Discount>  $applicableDiscounts
     */
    public function __construct(
        public PricedLine $line,
        public array $applicableDiscounts,
        public ?Discount $appliedDiscount,
    ) {
        $this->discountCents = $appliedDiscount === null
            ? 0
            : Money::percentOf($line->subtotalCents, $appliedDiscount->basisPoints);
        $this->totalCents = $line->subtotalCents - $this->discountCents;
    }
}
