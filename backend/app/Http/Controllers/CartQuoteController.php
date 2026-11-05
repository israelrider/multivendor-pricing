<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteCartRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Customer;
use App\Pricing\CartItem;
use App\Pricing\PricingService;

/**
 * Prices a cart for the cart and review screens. Persists nothing.
 */
class CartQuoteController extends Controller
{
    public function __invoke(QuoteCartRequest $request, PricingService $pricing): QuoteResource
    {
        $customer = Customer::findOrFail($request->validated('customer_id'));

        return new QuoteResource($pricing->quote($customer, CartItem::listFrom($request->validated('items'))));
    }
}
