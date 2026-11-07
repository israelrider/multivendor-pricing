<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates an empty order header. Real orders are built by OrderService, which
 * derives the totals from the line items. Tests that need totals set them explicitly.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'status' => OrderStatus::Pending,
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 0,
        ];
    }
}
