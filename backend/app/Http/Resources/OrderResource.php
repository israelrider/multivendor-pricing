<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Needs Order::RESOURCE_RELATIONS loaded.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer' => ['id' => $this->customer->id, 'name' => $this->customer->name],
            'status' => $this->status,
            'sub_orders' => SubOrderResource::collection($this->subOrders),
            'subtotal_cents' => $this->subtotal_cents,
            'discount_cents' => $this->discount_cents,
            'total_cents' => $this->total_cents,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
