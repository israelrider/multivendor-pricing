<?php

namespace App\Http\Resources;

use App\Models\SubOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Needs vendor and items loaded.
 *
 * @mixin SubOrder
 */
class SubOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor' => ['id' => $this->vendor->id, 'name' => $this->vendor->name],
            'status' => $this->status,
            'notified_at' => $this->notified_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->items),
            'subtotal_cents' => $this->subtotal_cents,
            'discount_cents' => $this->discount_cents,
            'total_cents' => $this->total_cents,
        ];
    }
}
