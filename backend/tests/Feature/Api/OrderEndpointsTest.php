<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Enums\SubOrderStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Jobs\NotifyVendorJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\SubOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class OrderEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = DatabaseSeeder::class;

    /**
     * Alex (loyal): ELEC-002 from Acme, BOOK-002 from Brightline. Two vendors.
     *   ELEC-002 ×2: 7 998, electronics 8% → 640 off → 7 358
     *   BOOK-002 ×1: 3 899, books 5%        → 195 off → 3 704
     *
     * @return array<string, mixed>
     */
    private function twoVendorCart(array $extra = []): array
    {
        $ids = Product::whereIn('sku', ['ELEC-002', 'BOOK-002'])->pluck('id', 'sku');

        return [
            'customer_id' => Customer::where('email', 'alex@example.com')->value('id'),
            'items' => [
                ['product_id' => $ids['ELEC-002'], 'quantity' => 2],
                ['product_id' => $ids['BOOK-002'], 'quantity' => 1],
            ],
            ...$extra,
        ];
    }

    public function test_an_order_from_two_vendors_is_split_into_two_sub_orders(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/orders', $this->twoVendorCart())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.customer.name', 'Alex Morgan')
            ->assertJsonPath('data.sub_orders.*.vendor.name', ['Acme Supply', 'Brightline Traders'])
            ->assertJsonPath('data.sub_orders.*.status', ['pending', 'pending'])
            ->assertJsonPath('data.subtotal_cents', 7_998 + 3_899)
            ->assertJsonPath('data.discount_cents', 640 + 195)
            ->assertJsonPath('data.total_cents', 7_358 + 3_704);

        $this->assertSame(1, Order::count());
        $this->assertSame(2, SubOrder::count());

        $acme = $response->json('data.sub_orders.0');
        $this->assertSame(['USB-C Charger 65W'], array_column($acme['items'], 'product_name'));
        $this->assertSame(['rule' => 'category', 'basis_points' => 800], $acme['items'][0]['discount']);
        $this->assertSame(7_358, $acme['total_cents']);

        $brightline = $response->json('data.sub_orders.1');
        $this->assertSame(['The Pragmatic Programmer'], array_column($brightline['items'], 'product_name'));

        Queue::assertPushed(NotifyVendorJob::class, 2);
        $subOrderIds = SubOrder::pluck('id')->all();
        Queue::assertPushed(NotifyVendorJob::class, fn (NotifyVendorJob $job) => in_array($job->subOrderId, $subOrderIds, true));
    }

    public function test_stored_totals_add_up_at_every_level(): void
    {
        Queue::fake();
        $ids = Product::pluck('id', 'sku');

        $this->postJson('/api/orders', [
            'customer_id' => Customer::where('email', 'jamie@example.com')->value('id'),
            'items' => [
                ['product_id' => $ids['ELEC-001'], 'quantity' => 3],
                ['product_id' => $ids['TOY-001'], 'quantity' => 12],
                ['product_id' => $ids['BOOK-001'], 'quantity' => 1],
                ['product_id' => $ids['HOME-002'], 'quantity' => 5],
                ['product_id' => $ids['ELEC-003'], 'quantity' => 1],
            ],
        ])->assertCreated();

        $order = Order::with('subOrders.items')->sole();

        $this->assertSame($order->subtotal_cents, $order->subOrders->sum('subtotal_cents'));
        $this->assertSame($order->discount_cents, $order->subOrders->sum('discount_cents'));
        $this->assertSame($order->total_cents, $order->subOrders->sum('total_cents'));
        $this->assertSame($order->total_cents, $order->subtotal_cents - $order->discount_cents);

        foreach ($order->subOrders as $subOrder) {
            $this->assertSame($subOrder->subtotal_cents, $subOrder->items->sum('line_subtotal_cents'));
            $this->assertSame($subOrder->discount_cents, $subOrder->items->sum('discount_cents'));
            $this->assertSame($subOrder->total_cents, $subOrder->items->sum('line_total_cents'));
        }
    }

    public function test_a_line_without_a_discount_stores_no_rule(): void
    {
        Queue::fake();

        $this->postJson('/api/orders', [
            'customer_id' => Customer::where('email', 'jamie@example.com')->value('id'),
            'items' => [['product_id' => Product::where('sku', 'TOY-002')->value('id'), 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('data.sub_orders.0.items.0.discount', null);

        $item = OrderItem::sole();
        $this->assertNull($item->discount_rule);
        $this->assertSame(0, $item->discount_bps);
        $this->assertSame(0, $item->discount_cents);
    }

    public function test_invalid_input_creates_nothing_and_pushes_no_job(): void
    {
        Queue::fake();

        $this->postJson('/api/orders', $this->twoVendorCart(['items' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertSame(0, Order::count());
        Queue::assertNothingPushed();
    }

    public function test_a_failed_transaction_rolls_back_and_pushes_no_job(): void
    {
        Queue::fake();
        OrderItem::creating(fn () => throw new RuntimeException('Simulated database failure.'));

        $this->withoutExceptionHandling();

        try {
            $this->postJson('/api/orders', $this->twoVendorCart());
            $this->fail('Expected the simulated failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated database failure.', $e->getMessage());
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(0, SubOrder::count());
        Queue::assertNothingPushed();
    }

    public function test_a_matching_expected_total_places_the_order(): void
    {
        Queue::fake();

        $this->postJson('/api/orders', $this->twoVendorCart(['expected_total_cents' => 11_062]))
            ->assertCreated();
    }

    public function test_a_stale_expected_total_returns_409_with_the_fresh_quote(): void
    {
        Queue::fake();

        // The customer reviewed the cart, then Brightline raised the book price above Acme's.
        ProductOffer::whereRelation('product', 'sku', 'BOOK-002')
            ->whereRelation('vendor', 'name', 'Brightline Traders')
            ->update(['price_cents' => 4_299]);

        $this->postJson('/api/orders', $this->twoVendorCart(['expected_total_cents' => 11_062]))
            ->assertConflict()
            ->assertJsonPath('expected_total_cents', 11_062)
            ->assertJsonPath('quote.lines.1.vendor.name', 'Acme Supply')     // now the cheaper book
            ->assertJsonPath('quote.lines.1.unit_price_cents', 4_199)
            ->assertJsonPath('quote.total_cents', 7_358 + 4_199 - 210)
            ->assertJsonStructure(['message', 'quote' => ['lines', 'vendor_groups', 'total_cents']]);

        $this->assertSame(0, Order::count());
        Queue::assertNothingPushed();
    }

    public function test_a_product_that_lost_its_last_offer_after_validation_still_gets_a_422(): void
    {
        Queue::fake();
        $payload = $this->twoVendorCart();

        // The offer is withdrawn right after the request passed validation, before pricing runs.
        $this->app->afterResolving(StoreOrderRequest::class, function () {
            ProductOffer::whereRelation('product', 'sku', 'BOOK-002')->update(['is_active' => false]);
        });

        $this->postJson('/api/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('product_id', Product::where('sku', 'BOOK-002')->value('id'));

        $this->assertSame(0, Order::count());
    }

    public function test_an_order_can_be_shown(): void
    {
        Queue::fake();
        $id = $this->postJson('/api/orders', $this->twoVendorCart())->assertCreated()->json('data.id');

        $this->getJson("/api/orders/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonCount(2, 'data.sub_orders')
            ->assertJsonPath('data.sub_orders.0.items.0.quantity', 2);
    }

    public function test_an_unknown_order_returns_404(): void
    {
        $this->getJson('/api/orders/999999')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_with_a_real_queue_the_order_ends_up_notified(): void
    {
        // phpunit.xml runs the sync queue, so the jobs run as soon as they are dispatched.
        $id = $this->postJson('/api/orders', $this->twoVendorCart())->assertCreated()->json('data.id');

        $this->getJson("/api/orders/{$id}")
            ->assertJsonPath('data.status', OrderStatus::Notified->value)
            ->assertJsonPath('data.sub_orders.*.status', [SubOrderStatus::Notified->value, SubOrderStatus::Notified->value]);
    }
}
