<?php

namespace App\Http\Controllers;

use App\Domain\Orders\OrderService;
use App\Domain\Pricing\CartItem;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    /**
     * Re-prices the cart on the server and places the order. 409 if the reviewed total is stale.
     * A repeated Idempotency-Key returns the existing order: 200 with `Idempotent-Replayed: true` instead of 201.
     */
    public function store(StoreOrderRequest $request, OrderService $orders): JsonResponse
    {
        $order = $orders->place(
            Customer::findOrFail($request->validated('customer_id')),
            CartItem::listFrom($request->validated('items')),
            $request->expectedTotalCents(),
            $request->idempotencyKey(),
        );

        $response = (new OrderResource($order->load(Order::RESOURCE_RELATIONS)))->response();

        return $order->wasRecentlyCreated
            ? $response->setStatusCode(201)
            : $response->setStatusCode(200)->header('Idempotent-Replayed', 'true');
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(Order::RESOURCE_RELATIONS));
    }
}
