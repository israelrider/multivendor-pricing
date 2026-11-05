<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductOffer;
use App\Domain\Pricing\OfferSelector;
use App\Domain\Pricing\UnpriceableProductException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A catalog product with its active offers (cheapest first) and the one a cart would use.
 * best_offer is null when no vendor sells it right now.
 * Needs category and offers.vendor loaded, with offers limited to active ones.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $best = $this->bestOffer();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ],
            'offers' => OfferResource::collection(
                $this->offers->sortBy([['price_cents', 'asc'], ['vendor_id', 'asc']])->values(),
            ),
            'best_offer' => $best === null ? null : new OfferResource($best),
        ];
    }

    /** The same choice the cart makes, so the catalog never advertises a different vendor. */
    private function bestOffer(): ?ProductOffer
    {
        try {
            return app(OfferSelector::class)->select($this->resource);
        } catch (UnpriceableProductException) {
            return null;
        }
    }
}
