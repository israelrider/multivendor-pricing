<?php

namespace Tests\Unit\Pricing;

use App\Domain\Discounts\Discount;
use App\Domain\Pricing\CartItem;
use App\Domain\Pricing\Quote;
use App\Domain\Pricing\QuotedLine;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Concerns\MakesPricedLines;

class QuoteTest extends TestCase
{
    use MakesPricedLines;

    public function test_a_quoted_line_applies_the_discount_to_its_subtotal(): void
    {
        $line = new QuotedLine($this->line(quantity: 3, unitPriceCents: 1_999), [], new Discount('x', 'X', 1_000));

        $this->assertSame(5_997, $line->line->subtotalCents);
        $this->assertSame(600, $line->discountCents);   // 599.7
        $this->assertSame(5_397, $line->totalCents);
    }

    public function test_a_line_without_a_discount_costs_its_subtotal(): void
    {
        $line = new QuotedLine($this->line(quantity: 2, unitPriceCents: 1_000), [], null);

        $this->assertSame(0, $line->discountCents);
        $this->assertSame(2_000, $line->totalCents);
    }

    public function test_lines_are_grouped_by_vendor_and_totals_add_up_to_the_cent(): void
    {
        $tenPercent = new Discount('x', 'X', 1_000);
        $quote = new Quote($this->context(), [
            new QuotedLine($this->line(quantity: 1, unitPriceCents: 1_005, vendorId: 3), [$tenPercent], $tenPercent),
            new QuotedLine($this->line(quantity: 1, unitPriceCents: 2_000, vendorId: 1), [], null),
            new QuotedLine($this->line(quantity: 3, unitPriceCents: 335, vendorId: 3), [$tenPercent], $tenPercent),
        ]);

        $this->assertSame([1, 3], array_map(fn ($g) => $g->vendorId, $quote->vendorGroups));
        $this->assertCount(2, $quote->vendorGroups[1]->lines);

        // 1005 → 101 off (100.5), 1005 → 101 off: rounding is per line, never on the group.
        $this->assertSame(202, $quote->vendorGroups[1]->discountCents);
        $this->assertSame(1_808, $quote->vendorGroups[1]->totalCents);

        $this->assertSame(4_010, $quote->subtotalCents);
        $this->assertSame(202, $quote->discountCents);
        $this->assertSame(3_808, $quote->totalCents);
        $this->assertSame(
            $quote->totalCents,
            array_sum(array_map(fn (QuotedLine $l) => $l->totalCents, $quote->lines)),
        );
    }

    public function test_an_empty_quote_is_zero(): void
    {
        $quote = new Quote($this->context(), []);

        $this->assertSame([], $quote->vendorGroups);
        $this->assertSame(0, $quote->totalCents);
    }

    public function test_cart_items_are_built_from_request_input(): void
    {
        $items = CartItem::listFrom([['product_id' => '4', 'quantity' => '2']]);

        $this->assertEquals([new CartItem(4, 2)], $items);
    }

    public function test_a_cart_item_needs_a_positive_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CartItem(1, 0);
    }
}
