<?php

namespace App\Http\Resources;

use App\Models\ProductOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Needs vendor loaded.
 *
 * @mixin ProductOffer
 */
class OfferResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor' => ['id' => $this->vendor->id, 'name' => $this->vendor->name],
            'price_cents' => $this->price_cents,
        ];
    }
}
