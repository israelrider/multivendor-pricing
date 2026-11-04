<?php

namespace Database\Factories;

use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\SubOrder;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubOrder>
 */
class SubOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'vendor_id' => Vendor::factory(),
            'status' => SubOrderStatus::Pending,
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 0,
            'notified_at' => null,
        ];
    }

    public function notified(): static
    {
        return $this->state([
            'status' => SubOrderStatus::Notified,
            'notified_at' => now(),
        ]);
    }
}
