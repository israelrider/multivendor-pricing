<?php

namespace Tests\Feature;

use App\Discounts\Discount;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Pricing\CartItem;
use App\Pricing\PricingService;
use App\Pricing\Quote;
use App\Pricing\QuotedLine;
use App\Pricing\UnpriceableProductException;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * PricingService end to end on the seeded demo catalog and the real config/discounts.php.
 */
class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = DatabaseSeeder::class;

    /** @param array<string, int> $cart sku => quantity */
    private function quote(string $customerEmail, array $cart): Quote
    {
        $products = Product::whereIn('sku', array_keys($cart))->pluck('id', 'sku');
        $items = array_map(fn (string $sku) => new CartItem($products[$sku], $cart[$sku]), array_keys($cart));

        return app(PricingService::class)->quote(Customer::where('email', $customerEmail)->firstOrFail(), $items);
    }

    private function lineFor(Quote $quote, string $sku): QuotedLine
    {
        foreach ($quote->lines as $line) {
            if ($line->line->sku === $sku) {
                return $line;
            }
        }

        $this->fail("No line for {$sku}.");
    }

    public function test_a_multi_vendor_cart_for_the_loyal_customer(): void
    {
        $quote = $this->quote('alex@example.com', ['ELEC-002' => 1, 'HOME-003' => 5, 'BOOK-002' => 10]);

        // ELEC-002: Acme/Corner tie at 39.99 → Acme. Electronics 8% beats loyalty 3%.
        $charger = $this->lineFor($quote, 'ELEC-002');
        $this->assertSame('Acme Supply', $charger->line->vendorName);
        $this->assertSame(3_999, $charger->line->unitPriceCents);
        $this->assertSame(['category', 'loyalty'], array_map(fn (Discount $d) => $d->rule, $charger->applicableDiscounts));
        $this->assertSame('category', $charger->appliedDiscount->rule);
        $this->assertSame(320, $charger->discountCents);            // 319.92
        $this->assertSame(3_679, $charger->totalCents);

        // HOME-003: the cheaper Acme offer is inactive → Corner. 5 units: 5% beats loyalty 3%.
        $skillet = $this->lineFor($quote, 'HOME-003');
        $this->assertSame('Corner Market', $skillet->line->vendorName);
        $this->assertSame(17_495, $skillet->line->subtotalCents);
        $this->assertSame('quantity', $skillet->appliedDiscount->rule);
        $this->assertSame(875, $skillet->discountCents);            // 874.75

        // BOOK-002: Brightline is cheaper. 10 units: 10% beats books 5% and loyalty 3%.
        $book = $this->lineFor($quote, 'BOOK-002');
        $this->assertSame('Brightline Traders', $book->line->vendorName);
        $this->assertSame(['quantity', 'category', 'loyalty'], array_map(fn (Discount $d) => $d->rule, $book->applicableDiscounts));
        $this->assertSame(3_899, $book->discountCents);

        // One group per vendor, in vendor id order (Acme, Brightline, Corner).
        $this->assertSame(
            ['Acme Supply', 'Brightline Traders', 'Corner Market'],
            array_map(fn ($g) => $g->vendorName, $quote->vendorGroups),
        );

        $this->assertSame(3_999 + 17_495 + 38_990, $quote->subtotalCents);
        $this->assertSame(320 + 875 + 3_899, $quote->discountCents);
        $this->assertSame($quote->subtotalCents - $quote->discountCents, $quote->totalCents);
        $this->assertSame(
            $quote->totalCents,
            array_sum(array_map(fn ($g) => $g->totalCents, $quote->vendorGroups)),
        );
    }

    public function test_lines_keep_the_cart_order(): void
    {
        $quote = $this->quote('jamie@example.com', ['TOY-001' => 1, 'BOOK-001' => 1, 'ELEC-001' => 1]);

        $this->assertSame(['TOY-001', 'BOOK-001', 'ELEC-001'], array_map(fn (QuotedLine $l) => $l->line->sku, $quote->lines));
    }

    public function test_a_regular_customer_gets_no_loyalty_discount(): void
    {
        $quote = $this->quote('jamie@example.com', ['TOY-002' => 1]);

        $line = $quote->lines[0];
        $this->assertSame([], $line->applicableDiscounts);
        $this->assertNull($line->appliedDiscount);
        $this->assertSame(1_599, $quote->totalCents);
    }

    public function test_the_loyal_customer_gets_loyalty_when_nothing_beats_it(): void
    {
        $quote = $this->quote('alex@example.com', ['TOY-002' => 1]);

        $this->assertSame('loyalty', $quote->lines[0]->appliedDiscount->rule);
        $this->assertSame(48, $quote->discountCents);               // 47.97
    }

    public function test_an_unknown_product_cannot_be_priced(): void
    {
        $this->expectException(UnpriceableProductException::class);
        $this->expectExceptionMessage('Product 999999 does not exist.');

        app(PricingService::class)->quote(Customer::firstOrFail(), [new CartItem(999_999, 1)]);
    }

    public function test_a_product_without_an_active_offer_cannot_be_priced(): void
    {
        $product = Product::factory()->create(['name' => 'Discontinued Lamp']);
        ProductOffer::factory()->for($product)->inactive()->create();

        try {
            app(PricingService::class)->quote(Customer::firstOrFail(), [new CartItem($product->id, 1)]);
            $this->fail('Expected UnpriceableProductException.');
        } catch (UnpriceableProductException $e) {
            $this->assertSame($product->id, $e->productId);
            $this->assertStringContainsString('Discontinued Lamp', $e->getMessage());
        }
    }

    public function test_a_product_may_appear_only_once(): void
    {
        $id = Product::firstOrFail()->id;

        $this->expectException(InvalidArgumentException::class);

        app(PricingService::class)->quote(Customer::firstOrFail(), [new CartItem($id, 1), new CartItem($id, 2)]);
    }
}
