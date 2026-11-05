<?php

namespace Tests\Unit\Discounts;

use App\Domain\Discounts\Combiners\BestDiscountWins;
use App\Domain\Discounts\Discount;
use App\Domain\Discounts\DiscountEngine;
use App\Domain\Discounts\DiscountRuleInterface;
use App\Domain\Discounts\Rules\CategoryDiscountRule;
use App\Domain\Discounts\Rules\LoyaltyCustomerDiscountRule;
use App\Domain\Discounts\Rules\QuantityThresholdDiscountRule;
use App\Domain\Pricing\PricedLine;
use App\Domain\Pricing\PricingContext;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Concerns\MakesPricedLines;

class DiscountEngineTest extends TestCase
{
    use MakesPricedLines;

    private function engine(DiscountRuleInterface ...$rules): DiscountEngine
    {
        return new DiscountEngine($rules, new BestDiscountWins);
    }

    private function fixed(string $key, ?int $bps): DiscountRuleInterface
    {
        return new class($key, $bps) implements DiscountRuleInterface
        {
            public function __construct(private string $key, private ?int $bps) {}

            public function key(): string
            {
                return $this->key;
            }

            public function evaluate(PricedLine $line, PricingContext $context): ?Discount
            {
                return $this->bps === null ? null : new Discount($this->key, $this->key, $this->bps);
            }
        };
    }

    public function test_best_wins_picks_the_highest_discount(): void
    {
        $chosen = (new BestDiscountWins)->choose([
            new Discount('a', 'A', 300), new Discount('b', 'B', 1000), new Discount('c', 'C', 800),
        ]);

        $this->assertSame('b', $chosen->rule);
    }

    public function test_best_wins_gives_a_tie_to_the_rule_registered_first(): void
    {
        $chosen = (new BestDiscountWins)->choose([new Discount('first', 'A', 500), new Discount('second', 'B', 500)]);

        $this->assertSame('first', $chosen->rule);
    }

    public function test_best_wins_with_nothing_applicable_applies_nothing(): void
    {
        $this->assertNull((new BestDiscountWins)->choose([]));
    }

    public function test_it_reports_every_applicable_discount_and_applies_one(): void
    {
        $result = $this->engine($this->fixed('a', 300), $this->fixed('skip', null), $this->fixed('b', 700))
            ->evaluate($this->line(), $this->context());

        $this->assertSame(['a', 'b'], array_map(fn (Discount $d) => $d->rule, $result->applicable));
        $this->assertSame('b', $result->applied->rule);
    }

    public function test_zero_percent_discounts_are_not_reported(): void
    {
        $result = $this->engine($this->fixed('zero', 0))->evaluate($this->line(), $this->context());

        $this->assertSame([], $result->applicable);
        $this->assertNull($result->applied);
    }

    public function test_with_no_rules_there_is_no_discount(): void
    {
        $result = $this->engine()->evaluate($this->line(), $this->context());

        $this->assertSame([], $result->applicable);
        $this->assertNull($result->applied);
    }

    public function test_it_accepts_any_iterable_of_rules(): void
    {
        $rules = (function () {
            yield $this->fixed('gen', 400);
        })();

        $result = (new DiscountEngine($rules, new BestDiscountWins))->evaluate($this->line(), $this->context());

        $this->assertSame('gen', $result->applied->rule);
    }

    /** The worked example from ADR 0002: loyal customer, 10 electronics items. */
    public function test_the_adr_worked_example(): void
    {
        $engine = $this->engine(
            new QuantityThresholdDiscountRule([5 => 500, 10 => 1000]),
            new CategoryDiscountRule(['electronics' => 800]),
            new LoyaltyCustomerDiscountRule(300),
        );

        $result = $engine->evaluate(
            $this->line(quantity: 10, unitPriceCents: 4_999, category: 'electronics'),
            $this->context(loyal: true),
        );

        $this->assertSame(['quantity', 'category', 'loyalty'], array_map(fn (Discount $d) => $d->rule, $result->applicable));
        $this->assertSame('quantity', $result->applied->rule);
        $this->assertSame(1000, $result->applied->basisPoints);
    }
}
