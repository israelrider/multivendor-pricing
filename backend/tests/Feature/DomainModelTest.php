<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\SubOrder;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_order_reaches_its_items_through_vendor_sub_orders(): void
    {
        $order = Order::factory()->create();
        [$a, $b] = SubOrder::factory()->count(2)->for($order)->create();
        OrderItem::factory()->count(2)->for($a)->create();
        OrderItem::factory()->for($b)->create();

        $order->load('subOrders.items', 'items');

        $this->assertCount(2, $order->subOrders);
        $this->assertCount(3, $order->items);
        $this->assertSame([2, 1], $order->subOrders->map(fn ($s) => $s->items->count())->all());
    }

    public function test_statuses_are_cast_to_enums(): void
    {
        $sub = SubOrder::factory()->create()->fresh();

        $this->assertSame(OrderStatus::Pending, $sub->order()->first()->status);
        $this->assertSame(SubOrderStatus::Pending, $sub->status);
        $this->assertNull($sub->notified_at);
    }

    public function test_money_columns_are_integers(): void
    {
        $item = OrderItem::factory()->create()->fresh();

        $this->assertIsInt($item->unit_price_cents);
        $this->assertIsInt($item->line_total_cents);
        $this->assertSame($item->unit_price_cents * $item->quantity, $item->line_subtotal_cents);
    }

    public function test_the_active_scope_excludes_inactive_offers(): void
    {
        $product = Product::factory()->create();
        ProductOffer::factory()->for($product)->create();
        ProductOffer::factory()->for($product)->inactive()->create();

        $this->assertSame(1, $product->offers()->active()->count());
    }

    public function test_a_vendor_can_have_only_one_offer_per_product(): void
    {
        $product = Product::factory()->create();
        $vendor = Vendor::factory()->create();
        ProductOffer::factory()->for($product)->for($vendor)->create();

        $this->expectException(QueryException::class);

        ProductOffer::factory()->for($product)->for($vendor)->create();
    }

    public function test_an_order_has_at_most_one_sub_order_per_vendor(): void
    {
        $order = Order::factory()->create();
        $vendor = Vendor::factory()->create();
        SubOrder::factory()->for($order)->for($vendor)->create();

        $this->expectException(QueryException::class);

        SubOrder::factory()->for($order)->for($vendor)->create();
    }
}
