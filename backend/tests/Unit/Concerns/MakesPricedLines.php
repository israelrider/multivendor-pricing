<?php

namespace Tests\Unit\Concerns;

use App\Pricing\PricedLine;
use App\Pricing\PricingContext;

trait MakesPricedLines
{
    protected function line(int $quantity = 1, int $unitPriceCents = 1000, string $category = 'toys', int $vendorId = 1): PricedLine
    {
        return new PricedLine(
            productId: 1,
            productName: 'Test product',
            sku: 'TEST-001',
            categorySlug: $category,
            categoryName: ucfirst($category),
            vendorId: $vendorId,
            vendorName: "Vendor {$vendorId}",
            unitPriceCents: $unitPriceCents,
            quantity: $quantity,
        );
    }

    protected function context(bool $loyal = false): PricingContext
    {
        return new PricingContext(customerId: 1, customerIsLoyal: $loyal);
    }
}
