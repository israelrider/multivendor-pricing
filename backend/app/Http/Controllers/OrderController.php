<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Orders\OrderService;
use App\Pricing\CartItem;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    /** Re-prices the cart on the server and places the order. 409 if the reviewed total is stale. */
    public function store(StoreOrderRequest $request, OrderService $orders): JsonResponse
    {
        $order = $orders->place(
            Customer::findOrFail($request->validated('customer_id')),
            CartItem::listFrom($request->validated('items')),
            $request->expectedTotalCents(),
        );

        return (new OrderResource($order->load(Order::RESOURCE_RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(Order::RESOURCE_RELATIONS));
    }
}
