<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SubOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An undiscounted line whose amounts are internally consistent
 * (line_subtotal = unit_price × quantity, line_total = line_subtotal − discount).
 *
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->numberBetween(500, 20_000);
        $subtotal = $quantity * $unitPrice;

        return [
            'sub_order_id' => SubOrder::factory(),
            'product_id' => Product::factory(),
            'product_name' => fn (array $attributes) => Product::find($attributes['product_id'])?->name ?? 'Product',
            'quantity' => $quantity,
            'unit_price_cents' => $unitPrice,
            'line_subtotal_cents' => $subtotal,
            'discount_rule' => null,
            'discount_bps' => 0,
            'discount_cents' => 0,
            'line_total_cents' => $subtotal,
        ];
    }
}
