<?php

namespace Tests\Unit\Discounts;

use App\Discounts\Discount;
use App\Discounts\Rules\CategoryDiscountRule;
use App\Discounts\Rules\LoyaltyCustomerDiscountRule;
use App\Discounts\Rules\QuantityThresholdDiscountRule;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Concerns\MakesPricedLines;

class DiscountRulesTest extends TestCase
{
    use MakesPricedLines;

    /** @return array<string, array{int, ?int}> */
    public static function quantities(): array
    {
        return [
            '1 unit' => [1, null],
            '4 units' => [4, null],
            '5 units' => [5, 500],
            '9 units' => [9, 500],
            '10 units' => [10, 1000],
            '11 units' => [11, 1000],
        ];
    }

    #[DataProvider('quantities')]
    public function test_quantity_tiers_at_the_boundaries(int $quantity, ?int $expectedBps): void
    {
        // Listed out of order on purpose: the rule must not depend on config order.
        $rule = new QuantityThresholdDiscountRule([10 => 1000, 5 => 500]);

        $discount = $rule->evaluate($this->line(quantity: $quantity), $this->context());

        $this->assertSame($expectedBps, $discount?->basisPoints);
    }

    public function test_quantity_discount_names_the_matched_tier(): void
    {
        $discount = (new QuantityThresholdDiscountRule([5 => 500, 10 => 1000]))
            ->evaluate($this->line(quantity: 12), $this->context());

        $this->assertSame('quantity', $discount->rule);
        $this->assertSame('10+ units: 10%', $discount->label);
    }

    public function test_quantity_rule_with_no_tiers_never_applies(): void
    {
        $this->assertNull((new QuantityThresholdDiscountRule([]))->evaluate($this->line(quantity: 999), $this->context()));
    }

    public function test_quantity_tiers_must_be_positive_integer_quantities(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QuantityThresholdDiscountRule([0 => 500]);
    }

    public function test_category_rule_applies_to_a_configured_category(): void
    {
        $discount = (new CategoryDiscountRule(['electronics' => 800]))
            ->evaluate($this->line(category: 'electronics'), $this->context());

        $this->assertSame('category', $discount->rule);
        $this->assertSame(800, $discount->basisPoints);
        $this->assertSame('Electronics: 8%', $discount->label);
    }

    public function test_category_rule_ignores_other_categories(): void
    {
        $this->assertNull((new CategoryDiscountRule(['electronics' => 800]))->evaluate($this->line(category: 'toys'), $this->context()));
    }

    public function test_loyalty_rule_applies_to_loyal_customers_only(): void
    {
        $rule = new LoyaltyCustomerDiscountRule(300);

        $discount = $rule->evaluate($this->line(), $this->context(loyal: true));
        $this->assertSame('loyalty', $discount->rule);
        $this->assertSame(300, $discount->basisPoints);
        $this->assertSame('Loyal customer: 3%', $discount->label);

        $this->assertNull($rule->evaluate($this->line(), $this->context(loyal: false)));
    }

    public function test_loyalty_rule_with_zero_bps_is_off(): void
    {
        $this->assertNull((new LoyaltyCustomerDiscountRule(0))->evaluate($this->line(), $this->context(loyal: true)));
    }

    public function test_a_discount_is_limited_to_0_to_100_percent(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Discount('x', 'X', 10_001);
    }

    public function test_percent_labels(): void
    {
        $this->assertSame('10%', Discount::formatPercent(1000));
        $this->assertSame('2.5%', Discount::formatPercent(250));
        $this->assertSame('0.25%', Discount::formatPercent(25));
    }
}
