<?php

namespace App\Domain\Orders;

use App\Enums\OrderStatus;
use App\Enums\SubOrderStatus;
use App\Jobs\NotifyVendorJob;
use App\Models\Customer;
use App\Models\Order;
use App\Domain\Pricing\CartItem;
use App\Domain\Pricing\PricingService;
use App\Domain\Pricing\QuotedLine;
use App\Domain\Pricing\UnpriceableProductException;
use Illuminate\Support\Facades\DB;

/**
 * Re-prices the cart on the server and persists it as one order with a sub-order per vendor.
 */
final class OrderService
{
    public function __construct(private readonly PricingService $pricing) {}

    /**
     * @param  list<CartItem>  $items
     * @param  ?int  $expectedTotalCents  the total the customer reviewed; null skips the drift check
     *
     * @throws QuoteChangedException when the fresh total differs from $expectedTotalCents
     * @throws UnpriceableProductException
     */
    public function place(Customer $customer, array $items, ?int $expectedTotalCents = null): Order
    {
        $quote = $this->pricing->quote($customer, $items);

        if ($expectedTotalCents !== null && $expectedTotalCents !== $quote->totalCents) {
            throw new QuoteChangedException($quote, $expectedTotalCents);
        }

        $subOrderIds = [];

        $order = DB::transaction(function () use ($customer, $quote, &$subOrderIds) {
            $order = $customer->orders()->create([
                'status' => OrderStatus::Pending,
                'subtotal_cents' => $quote->subtotalCents,
                'discount_cents' => $quote->discountCents,
                'total_cents' => $quote->totalCents,
            ]);

            foreach ($quote->vendorGroups as $group) {
                $subOrder = $order->subOrders()->create([
                    'vendor_id' => $group->vendorId,
                    'status' => SubOrderStatus::Pending,
                    'subtotal_cents' => $group->subtotalCents,
                    'discount_cents' => $group->discountCents,
                    'total_cents' => $group->totalCents,
                ]);
                $subOrderIds[] = $subOrder->id;

                $subOrder->items()->createMany(array_map(fn (QuotedLine $line) => [
                    'product_id' => $line->line->productId,
                    'product_name' => $line->line->productName,
                    'quantity' => $line->line->quantity,
                    'unit_price_cents' => $line->line->unitPriceCents,
                    'line_subtotal_cents' => $line->line->subtotalCents,
                    'discount_rule' => $line->appliedDiscount?->rule,
                    'discount_bps' => $line->appliedDiscount?->basisPoints ?? 0,
                    'discount_cents' => $line->discountCents,
                    'line_total_cents' => $line->totalCents,
                ], $group->lines));
            }

            return $order;
        });

        // Dispatched once the transaction has committed, so a rollback never notifies a vendor.
        // The job is also ShouldQueueAfterCommit, which covers callers that wrap this in their own transaction.
        foreach ($subOrderIds as $subOrderId) {
            NotifyVendorJob::dispatch($subOrderId);
        }

        return $order;
    }
}
