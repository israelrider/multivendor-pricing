<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The snapshot taken at checkout. discount is null when no rule applied.
 *
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'unit_price_cents' => $this->unit_price_cents,
            'subtotal_cents' => $this->line_subtotal_cents,
            'discount' => $this->discount_rule === null ? null : [
                'rule' => $this->discount_rule,
                'basis_points' => $this->discount_bps,
            ],
            'discount_cents' => $this->discount_cents,
            'total_cents' => $this->line_total_cents,
        ];
    }
}
