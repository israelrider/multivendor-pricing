<?php

namespace Tests\Feature;

use App\Discounts\Combiners\BestDiscountWins;
use App\Discounts\Discount;
use App\Discounts\DiscountCombiner;
use App\Discounts\DiscountEngine;
use App\Providers\DiscountServiceProvider;
use Tests\Fixtures\FlashSaleDiscountRule;
use Tests\TestCase;
use Tests\Unit\Concerns\MakesPricedLines;

/**
 * The container wiring from config/discounts.php, and that rules and policies plug in without code changes.
 */
class DiscountWiringTest extends TestCase
{
    use MakesPricedLines;

    public function test_the_engine_runs_the_configured_rules(): void
    {
        $result = app(DiscountEngine::class)->evaluate(
            $this->line(quantity: 10, unitPriceCents: 4_999, category: 'electronics'),
            $this->context(loyal: true),
        );

        $this->assertSame(['quantity', 'category', 'loyalty'], array_map(fn (Discount $d) => $d->rule, $result->applicable));
        $this->assertSame('quantity', $result->applied->rule);
    }

    public function test_the_default_combiner_is_best_wins(): void
    {
        $this->assertInstanceOf(BestDiscountWins::class, app(DiscountCombiner::class));
    }

    public function test_a_newly_tagged_rule_is_picked_up_with_no_other_change(): void
    {
        $this->app->tag([FlashSaleDiscountRule::class], DiscountServiceProvider::RULES_TAG);

        $result = app(DiscountEngine::class)->evaluate($this->line(quantity: 10), $this->context());

        $this->assertSame(['quantity', 'flash_sale'], array_map(fn (Discount $d) => $d->rule, $result->applicable));
        $this->assertSame('flash_sale', $result->applied->rule);
    }

    public function test_the_combining_policy_can_be_swapped_by_binding(): void
    {
        // A "smallest discount wins" policy, only to show the seam.
        $this->app->bind(DiscountCombiner::class, fn () => new class implements DiscountCombiner
        {
            public function choose(array $applicable): ?Discount
            {
                usort($applicable, fn (Discount $a, Discount $b) => $a->basisPoints <=> $b->basisPoints);

                return $applicable[0] ?? null;
            }
        });

        $result = app(DiscountEngine::class)->evaluate(
            $this->line(quantity: 10, category: 'electronics'),
            $this->context(loyal: true),
        );

        $this->assertSame('loyalty', $result->applied->rule);
    }
}
