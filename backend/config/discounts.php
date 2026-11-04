<?php

use App\Discounts\Combiners\BestDiscountWins;
use App\Discounts\Rules\CategoryDiscountRule;
use App\Discounts\Rules\LoyaltyCustomerDiscountRule;
use App\Discounts\Rules\QuantityThresholdDiscountRule;

/*
|--------------------------------------------------------------------------
| Discount rules
|--------------------------------------------------------------------------
|
| Every class in "rules" implements DiscountRuleInterface and is evaluated on every cart line.
| Adding a rule is a new class plus one line here. Nothing else changes.
| The order matters only for ties: with equal percentages the rule listed first is applied.
|
| All percentages are integer basis points: 100 bps = 1%, 1000 bps = 10%.
| See docs/decisions/0002-discount-combining-best-wins.md.
|
*/

return [

    'rules' => [
        QuantityThresholdDiscountRule::class,
        CategoryDiscountRule::class,
        LoyaltyCustomerDiscountRule::class,
    ],

    // Picks the one discount applied per line from all applicable ones (DiscountCombiner).
    'combiner' => BestDiscountWins::class,

    // Minimum line quantity => bps. The highest matching tier wins.
    'quantity_tiers' => [
        5 => 500,
        10 => 1000,
    ],

    // Category slug => bps. Unlisted categories get no category discount.
    'categories' => [
        'electronics' => 800,
        'books' => 500,
    ],

    // Customers with is_loyal = true.
    'loyalty_bps' => 300,

];
