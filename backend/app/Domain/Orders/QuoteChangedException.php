<?php

namespace App\Domain\Orders;

use App\Domain\Pricing\Quote;
use RuntimeException;

/**
 * The cart's price moved between the review screen and checkout. Carries the fresh quote
 * so the client can show it and ask the customer to review again (409).
 */
final class QuoteChangedException extends RuntimeException
{
    public function __construct(public readonly Quote $quote, public readonly int $expectedTotalCents)
    {
        parent::__construct('Prices have changed since you reviewed your cart. Please review the updated total.');
    }
}
