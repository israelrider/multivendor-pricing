<?php

namespace App\Domain\Pricing;

use App\Models\Product;
use App\Models\ProductOffer;

/**
 * A cart line after the offer is chosen and before any discount: what discount rules look at.
 */
final readonly class PricedLine
{
    public int $subtotalCents;

    public function __construct(
        public int $productId,
        public string $productName,
        public string $sku,
        public string $categorySlug,
        public string $categoryName,
        public int $vendorId,
        public string $vendorName,
        public int $unitPriceCents,
        public int $quantity,
    ) {
        $this->subtotalCents = $unitPriceCents * $quantity;
    }

    /** Needs $product->category and $offer->vendor loaded. */
    public static function from(Product $product, ProductOffer $offer, int $quantity): self
    {
        return new self(
            productId: $product->id,
            productName: $product->name,
            sku: $product->sku,
            categorySlug: $product->category->slug,
            categoryName: $product->category->name,
            vendorId: $offer->vendor_id,
            vendorName: $offer->vendor->name,
            unitPriceCents: $offer->price_cents,
            quantity: $quantity,
        );
    }
}
