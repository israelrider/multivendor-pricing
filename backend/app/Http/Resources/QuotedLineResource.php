<?php

namespace App\Http\Resources;

use App\Discounts\Discount;
use App\Pricing\QuotedLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * applicable_discounts lists every rule that matched, with the applied one flagged,
 * so the UI can show "also eligible" next to the discount that won.
 *
 * @mixin QuotedLine
 */
class QuotedLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $line = $this->line;

        return [
            'product_id' => $line->productId,
            'product_name' => $line->productName,
            'sku' => $line->sku,
            'category' => ['slug' => $line->categorySlug, 'name' => $line->categoryName],
            'vendor' => ['id' => $line->vendorId, 'name' => $line->vendorName],
            'unit_price_cents' => $line->unitPriceCents,
            'quantity' => $line->quantity,
            'subtotal_cents' => $line->subtotalCents,
            'applied_discount' => $this->appliedDiscount === null ? null : new DiscountResource($this->appliedDiscount),
            'applicable_discounts' => array_map(fn (Discount $discount) => [
                ...(new DiscountResource($discount))->resolve($request),
                'applied' => $discount === $this->appliedDiscount,
            ], $this->applicableDiscounts),
            'discount_cents' => $this->discountCents,
            'total_cents' => $this->totalCents,
        ];
    }
}
