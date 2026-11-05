<?php

namespace App\Domain\Orders;

use App\Domain\Pricing\CartItem;
use App\Domain\Pricing\PricingService;
use App\Domain\Pricing\Quote;
use App\Domain\Pricing\QuotedLine;
use App\Domain\Pricing\UnpriceableProductException;
use App\Enums\OrderStatus;
use App\Enums\SubOrderStatus;
use App\Jobs\NotifyVendorJob;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Re-prices the cart on the server and persists it as one order with a sub-order per vendor.
 *
 * With an idempotency key, placing the same cart again returns the order that key already created,
 * without re-pricing it or notifying any vendor a second time. `$order->wasRecentlyCreated` tells a
 * new order from a replayed one.
 */
final class OrderService
{
    public function __construct(private readonly PricingService $pricing) {}

    /**
     * @param  list<CartItem>  $items
     * @param  ?int  $expectedTotalCents  the total the customer reviewed; null skips the drift check
     * @param  ?string  $idempotencyKey  a client-chosen key for this checkout; null places the order unconditionally
     *
     * @throws QuoteChangedException when the fresh total differs from $expectedTotalCents
     * @throws IdempotencyKeyReusedException when the key belongs to an order for a different customer or cart
     * @throws UnpriceableProductException
     */
    public function place(Customer $customer, array $items, ?int $expectedTotalCents = null, ?string $idempotencyKey = null): Order
    {
        $requestHash = $idempotencyKey === null ? null : self::requestHash($customer, $items);

        // A retry or a second click: hand back the order this key already placed.
        if ($idempotencyKey !== null && ($existing = $this->findByKey($idempotencyKey, $requestHash)) !== null) {
            return $existing;
        }

        $quote = $this->pricing->quote($customer, $items);

        if ($expectedTotalCents !== null && $expectedTotalCents !== $quote->totalCents) {
            throw new QuoteChangedException($quote, $expectedTotalCents);
        }

        try {
            return $this->persist($customer, $quote, $idempotencyKey, $requestHash);
        } catch (UniqueConstraintViolationException $e) {
            // Two requests with the same key both passed the lookup above. The unique index let only one
            // insert through; this transaction rolled back, so return the order the other request created.
            if ($idempotencyKey !== null && ($existing = $this->findByKey($idempotencyKey, $requestHash)) !== null) {
                return $existing;
            }

            throw $e;
        }
    }

    /**
     * The order already placed with this key, or null if there is none yet.
     *
     * @throws IdempotencyKeyReusedException
     */
    private function findByKey(string $idempotencyKey, string $requestHash): ?Order
    {
        $order = Order::firstWhere('idempotency_key', $idempotencyKey);

        if ($order !== null && ! hash_equals((string) $order->idempotency_request_hash, $requestHash)) {
            throw new IdempotencyKeyReusedException($idempotencyKey);
        }

        return $order;
    }

    /**
     * What a key stands for: this customer, these products, these quantities. The expected total is left out
     * on purpose. It is a precondition for placing the order, not part of the order.
     *
     * @param  list<CartItem>  $items
     */
    private static function requestHash(Customer $customer, array $items): string
    {
        return hash('sha256', json_encode([
            'customer_id' => $customer->id,
            'items' => array_map(fn (CartItem $item) => [$item->productId, $item->quantity], $items),
        ], JSON_THROW_ON_ERROR));
    }

    private function persist(Customer $customer, Quote $quote, ?string $idempotencyKey, ?string $requestHash): Order
    {
        $subOrderIds = [];

        $order = DB::transaction(function () use ($customer, $quote, $idempotencyKey, $requestHash, &$subOrderIds) {
            $order = $customer->orders()->create([
                'idempotency_key' => $idempotencyKey,
                'idempotency_request_hash' => $requestHash,
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
