<?php

namespace App\Pricing;

use App\Models\Customer;

/**
 * Facts about the whole cart that rules may use. Plain values, so rules are testable without a database.
 */
final readonly class PricingContext
{
    public function __construct(
        public int $customerId,
        public bool $customerIsLoyal,
    ) {}

    public static function forCustomer(Customer $customer): self
    {
        return new self($customer->id, $customer->is_loyal);
    }
}
