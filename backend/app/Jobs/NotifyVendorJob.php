<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\SubOrder;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Tells one vendor about its part of an order. Here "telling" is a structured log entry.
 *
 * Idempotent: a sub-order that already has notified_at is skipped, so a retry never notifies twice.
 * When the last sub-order of an order is notified, the order itself becomes notified.
 */
class NotifyVendorJob implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds before each retry */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $subOrderId) {}

    public function handle(): void
    {
        $subOrder = SubOrder::with(['vendor', 'items'])->find($this->subOrderId);

        if ($subOrder === null) {
            Log::warning('Vendor notification skipped: sub-order not found', ['sub_order_id' => $this->subOrderId]);

            return;
        }

        if ($subOrder->notified_at !== null) {
            return;
        }

        Log::info('Vendor notified', [
            'order_id' => $subOrder->order_id,
            'sub_order_id' => $subOrder->id,
            'vendor_id' => $subOrder->vendor_id,
            'vendor_name' => $subOrder->vendor->name,
            'vendor_email' => $subOrder->vendor->email,
            'items' => $subOrder->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'line_total_cents' => $item->line_total_cents,
            ])->all(),
            'total_cents' => $subOrder->total_cents,
        ]);

        $subOrder->update([
            'status' => SubOrderStatus::Notified,
            'notified_at' => now(),
        ]);

        // One conditional UPDATE, so two jobs of the same order finishing together can't miss the flip.
        Order::query()
            ->whereKey($subOrder->order_id)
            ->where('status', OrderStatus::Pending)
            ->whereDoesntHave('subOrders', fn ($query) => $query->whereNull('notified_at'))
            ->update(['status' => OrderStatus::Notified]);
    }
}
