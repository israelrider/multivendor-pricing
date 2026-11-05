<?php

namespace App\Http\Resources;

use App\Domain\Pricing\Quote;
use App\Domain\Pricing\VendorGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * lines keep the cart order (for the cart page). vendor_groups hold the same lines grouped
 * by vendor id (for the review page). Every amount is in cents.
 *
 * @mixin Quote
 */
class QuoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this->context->customerId,
            'lines' => QuotedLineResource::collection($this->lines),
            'vendor_groups' => array_map(fn (VendorGroup $group) => [
                'vendor' => ['id' => $group->vendorId, 'name' => $group->vendorName],
                'lines' => QuotedLineResource::collection($group->lines),
                'subtotal_cents' => $group->subtotalCents,
                'discount_cents' => $group->discountCents,
                'total_cents' => $group->totalCents,
            ], $this->vendorGroups),
            'subtotal_cents' => $this->subtotalCents,
            'discount_cents' => $this->discountCents,
            'total_cents' => $this->totalCents,
        ];
    }
}
