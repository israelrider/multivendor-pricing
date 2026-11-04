<?php

namespace App\Discounts;

/**
 * The outcome of evaluating every rule on one line.
 */
final readonly class DiscountResult
{
    /**
     * @param  list<Discount>  $applicable  every rule that applied, in registration order
     * @param  ?Discount  $applied  the one the combiner picked (one of $applicable), or null
     */
    public function __construct(
        public array $applicable,
        public ?Discount $applied,
    ) {}
}
