<?php

namespace App\Domain\Pricing;

use App\Models\Product;
use App\Models\ProductOffer;

/**
 * Picks the vendor offer a product is bought from: the active offer with the lowest price,
 * with ties going to the lowest vendor id so the choice is deterministic.
 *
 * Comparing base prices is correct only because no discount rule depends on the vendor, so the
 * cheapest base price is also the cheapest final price. If a vendor-specific rule is added,
 * this must compare post-discount line totals instead (run DiscountEngine per candidate offer).
 */
final class OfferSelector
{
    /**
     * Needs $product->offers loaded.
     *
     * @throws UnpriceableProductException when no offer is active
     */
    public function select(Product $product): ProductOffer
    {
        $best = null;

        foreach ($product->offers as $offer) {
            if (! $offer->is_active) {
                continue;
            }

            if ($best === null
                || $offer->price_cents < $best->price_cents
                || ($offer->price_cents === $best->price_cents && $offer->vendor_id < $best->vendor_id)) {
                $best = $offer;
            }
        }

        return $best ?? throw UnpriceableProductException::noActiveOffer($product->id, $product->name);
    }
}
