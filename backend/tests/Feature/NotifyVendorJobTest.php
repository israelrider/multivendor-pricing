<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\SubOrderStatus;
use App\Jobs\NotifyVendorJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SubOrder;
use App\Models\Vendor;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class NotifyVendorJobTest extends TestCase
{
    use RefreshDatabase;

    private function subOrder(?Order $order = null): SubOrder
    {
        $subOrder = SubOrder::factory()
            ->for($order ?? Order::factory())
            ->for(Vendor::factory())
            ->create(['total_cents' => 2_500]);

        OrderItem::factory()->for($subOrder)->create([
            'product_name' => 'Wireless Mouse',
            'quantity' => 1,
            'line_total_cents' => 2_500,
        ]);

        return $subOrder;
    }

    public function test_it_is_queued_only_after_the_transaction_commits(): void
    {
        $this->assertInstanceOf(ShouldQueueAfterCommit::class, new NotifyVendorJob(1));
    }

    public function test_it_logs_the_notification_and_marks_the_sub_order_notified(): void
    {
        Log::spy();
        $subOrder = $this->subOrder();

        (new NotifyVendorJob($subOrder->id))->handle();

        Log::shouldHaveReceived('info')->once()->with('Vendor notified', Mockery::on(fn (array $context) => $context['sub_order_id'] === $subOrder->id
            && $context['vendor_email'] === $subOrder->vendor->email
            && $context['items'][0]['product_name'] === 'Wireless Mouse'
            && $context['total_cents'] === 2_500));

        $subOrder->refresh();
        $this->assertSame(SubOrderStatus::Notified, $subOrder->status);
        $this->assertNotNull($subOrder->notified_at);
    }

    public function test_a_rerun_does_not_notify_twice(): void
    {
        Log::spy();
        $subOrder = $this->subOrder();

        (new NotifyVendorJob($subOrder->id))->handle();
        $notifiedAt = $subOrder->refresh()->notified_at;

        $this->travel(5)->minutes();
        (new NotifyVendorJob($subOrder->id))->handle();

        Log::shouldHaveReceived('info')->once();
        $this->assertEquals($notifiedAt, $subOrder->refresh()->notified_at);
    }

    public function test_the_order_becomes_notified_only_when_its_last_sub_order_is(): void
    {
        Log::spy();
        $order = Order::factory()->create();
        $first = $this->subOrder($order);
        $second = $this->subOrder($order);

        (new NotifyVendorJob($first->id))->handle();
        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);

        (new NotifyVendorJob($second->id))->handle();
        $this->assertSame(OrderStatus::Notified, $order->refresh()->status);
    }

    public function test_a_missing_sub_order_is_skipped_with_a_warning(): void
    {
        Log::spy();

        (new NotifyVendorJob(999_999))->handle();

        Log::shouldHaveReceived('warning')->once();
        Log::shouldNotHaveReceived('info');
    }

    public function test_it_retries_with_backoff(): void
    {
        $job = new NotifyVendorJob(1);

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60], $job->backoff);
    }
}
