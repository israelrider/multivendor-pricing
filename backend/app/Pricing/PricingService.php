<?php

namespace App\Pricing;

use App\Discounts\DiscountEngine;
use App\Models\Customer;
use App\Models\Product;
use InvalidArgumentException;

/**
 * The single source of truth for prices. Used by both the cart quote endpoint (persists nothing)
 * and order creation (re-prices on the server, then persists the Quote).
 *
 * Cart → OfferSelector (cheapest active offer) → DiscountEngine (best rule per line) → Quote.
 */
final class PricingService
{
    public function __construct(
        private readonly OfferSelector $offerSelector,
        private readonly DiscountEngine $discountEngine,
    ) {}

    /**
     * @param  list<CartItem>  $items
     *
     * @throws UnpriceableProductException
     */
    public function quote(Customer $customer, array $items): Quote
    {
        $productIds = array_map(fn (CartItem $item) => $item->productId, $items);

        if (count($productIds) !== count(array_unique($productIds))) {
            throw new InvalidArgumentException('Each product may appear only once in a cart.');
        }

        $products = Product::query()
            ->with(['category', 'offers' => fn ($query) => $query->active()->with('vendor')])
            ->findMany($productIds)
            ->keyBy('id');

        $context = PricingContext::forCustomer($customer);
        $lines = [];

        foreach ($items as $item) {
            $product = $products->get($item->productId)
                ?? throw UnpriceableProductException::unknownProduct($item->productId);

            $line = PricedLine::from($product, $this->offerSelector->select($product), $item->quantity);
            $discounts = $this->discountEngine->evaluate($line, $context);

            $lines[] = new QuotedLine($line, $discounts->applicable, $discounts->applied);
        }

        return new Quote($context, $lines);
    }
}
