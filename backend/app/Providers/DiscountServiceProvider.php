<?php

namespace App\Providers;

use App\Domain\Discounts\DiscountCombiner;
use App\Domain\Discounts\DiscountEngine;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the discount engine from config/discounts.php: the listed rules are tagged
 * "discount.rules" and injected into DiscountEngine, and the configured combiner is bound.
 * Rules read their own settings through #[Config] constructor attributes.
 */
class DiscountServiceProvider extends ServiceProvider
{
    public const string RULES_TAG = 'discount.rules';

    public function register(): void
    {
        $this->app->tag(config('discounts.rules', []), self::RULES_TAG);

        $this->app->bind(DiscountCombiner::class, fn ($app) => $app->make(config('discounts.combiner')));

        $this->app->when(DiscountEngine::class)->needs('$rules')->giveTagged(self::RULES_TAG);
    }
}
