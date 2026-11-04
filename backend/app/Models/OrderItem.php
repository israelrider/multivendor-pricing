<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One priced line. product_name, unit_price_cents and the discount columns are a snapshot
 * taken when the order was placed. They are never recomputed from the catalog.
 */
#[Fillable([
    'sub_order_id', 'product_id', 'product_name', 'quantity',
    'unit_price_cents', 'line_subtotal_cents',
    'discount_rule', 'discount_bps', 'discount_cents', 'line_total_cents',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'line_subtotal_cents' => 'integer',
            'discount_bps' => 'integer',
            'discount_cents' => 'integer',
            'line_total_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<SubOrder, $this> */
    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
