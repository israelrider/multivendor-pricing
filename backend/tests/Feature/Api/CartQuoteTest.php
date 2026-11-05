<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOffer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CartQuoteTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = DatabaseSeeder::class;

    /**
     * @param  array<string, int>  $cart  sku => quantity
     * @return array<string, mixed>
     */
    private function payload(string $customerEmail, array $cart): array
    {
        $ids = Product::whereIn('sku', array_keys($cart))->pluck('id', 'sku');

        return [
            'customer_id' => Customer::where('email', $customerEmail)->value('id'),
            'items' => array_map(
                fn (string $sku) => ['product_id' => $ids[$sku], 'quantity' => $cart[$sku]],
                array_keys($cart),
            ),
        ];
    }

    public function test_it_prices_a_multi_vendor_cart(): void
    {
        $response = $this->postJson('/api/cart/quote', $this->payload('alex@example.com', [
            'ELEC-002' => 1, 'HOME-003' => 5, 'BOOK-002' => 10,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.subtotal_cents', 3_999 + 17_495 + 38_990)
            ->assertJsonPath('data.discount_cents', 320 + 875 + 3_899)
            ->assertJsonPath('data.total_cents', 60_484 - 5_094)
            ->assertJsonPath('data.lines.*.sku', ['ELEC-002', 'HOME-003', 'BOOK-002'])
            ->assertJsonPath('data.vendor_groups.*.vendor.name', ['Acme Supply', 'Brightline Traders', 'Corner Market']);

        $charger = $response->json('data.lines.0');
        $this->assertSame(['id' => 1, 'name' => 'Acme Supply'], $charger['vendor']);
        $this->assertSame('category', $charger['applied_discount']['rule']);
        $this->assertSame(
            [['category', true], ['loyalty', false]],
            array_map(fn (array $d) => [$d['rule'], $d['applied']], $charger['applicable_discounts']),
        );
    }

    public function test_group_totals_add_up_to_the_quote_total(): void
    {
        $data = $this->postJson('/api/cart/quote', $this->payload('jamie@example.com', [
            'ELEC-001' => 3, 'TOY-001' => 12, 'BOOK-001' => 1, 'HOME-001' => 5,
        ]))->assertOk()->json('data');

        $this->assertSame($data['total_cents'], array_sum(array_column($data['vendor_groups'], 'total_cents')));

        foreach ($data['vendor_groups'] as $group) {
            $this->assertSame($group['total_cents'], array_sum(array_column($group['lines'], 'total_cents')));
        }
    }

    public function test_a_quote_persists_nothing(): void
    {
        $this->postJson('/api/cart/quote', $this->payload('alex@example.com', ['TOY-002' => 1]))->assertOk();

        $this->assertSame(0, Order::count());
    }

    /** @return iterable<string, array{0: callable(array<string, mixed>): array<string, mixed>, 1: string}> */
    public static function invalidCarts(): iterable
    {
        yield 'missing customer' => [fn (array $p) => [...$p, 'customer_id' => null], 'customer_id'];
        yield 'unknown customer' => [fn (array $p) => [...$p, 'customer_id' => 999_999], 'customer_id'];
        yield 'no items' => [fn (array $p) => [...$p, 'items' => []], 'items'];
        yield 'items not a list' => [fn (array $p) => [...$p, 'items' => 'TOY-002'], 'items'];
        yield 'zero quantity' => [fn (array $p) => [...$p, 'items' => [[...$p['items'][0], 'quantity' => 0]]], 'items.0.quantity'];
        yield 'quantity too high' => [fn (array $p) => [...$p, 'items' => [[...$p['items'][0], 'quantity' => 1000]]], 'items.0.quantity'];
        yield 'fractional quantity' => [fn (array $p) => [...$p, 'items' => [[...$p['items'][0], 'quantity' => 1.5]]], 'items.0.quantity'];
        yield 'missing product id' => [fn (array $p) => [...$p, 'items' => [['quantity' => 1]]], 'items.0.product_id'];
        yield 'unknown product' => [fn (array $p) => [...$p, 'items' => [['product_id' => 999_999, 'quantity' => 1]]], 'items.0.product_id'];
        yield 'duplicate product' => [fn (array $p) => [...$p, 'items' => [$p['items'][0], $p['items'][0]]], 'items.1.product_id'];
        yield 'a price sent by the client' => [fn (array $p) => [...$p, 'items' => [[...$p['items'][0], 'price_cents' => 1]]], 'items.0'];
        yield 'too many items' => [fn (array $p) => [...$p, 'items' => array_map(
            fn (int $id) => ['product_id' => $id, 'quantity' => 1],
            range(1, 51),
        )], 'items'];
    }

    #[DataProvider('invalidCarts')]
    public function test_invalid_carts_are_rejected(callable $mutate, string $errorKey): void
    {
        $payload = $mutate($this->payload('alex@example.com', ['TOY-002' => 1]));

        $this->postJson('/api/cart/quote', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorKey]);
    }

    public function test_a_product_without_an_active_offer_is_rejected_with_its_name(): void
    {
        $product = Product::factory()->create(['name' => 'Discontinued Lamp']);
        ProductOffer::factory()->for($product)->inactive()->create();

        $payload = $this->payload('alex@example.com', ['TOY-002' => 1]);
        $payload['items'][] = ['product_id' => $product->id, 'quantity' => 1];

        $this->postJson('/api/cart/quote', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.1.product_id' => '"Discontinued Lamp" is not available from any vendor right now.',
            ])
            ->assertJsonMissingValidationErrors(['items.0.product_id']);
    }
}
