import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { ApiError } from '../../api/client'
import { hasPendingSubOrders, useOrder } from '../../api/hooks'
import type { Order, Status, SubOrder } from '../../api/types'
import { Badge, Button, Card, ErrorBanner, Money, PageHeader, Skeleton, Spinner, TotalsList } from '../../components/ui'
import { discountLabel, formatDateTime } from '../../lib/format'

/** After this long with sub-orders still pending, the queue worker is probably not running. */
const WORKER_HINT_AFTER_MS = 15_000

export function OrderPage() {
  const { id } = useParams()
  const orderId = Number(id)

  if (!Number.isInteger(orderId) || orderId <= 0) return <OrderNotFound />

  return <OrderDetails orderId={orderId} />
}

function OrderDetails({ orderId }: { orderId: number }) {
  const order = useOrder(orderId)

  if (order.isPending) return <OrderSkeleton />

  if (order.isError) {
    if (order.error instanceof ApiError && order.error.status === 404) return <OrderNotFound />
    return (
      <ErrorBanner
        title="Couldn't load this order."
        action={
          <Button variant="secondary" size="sm" onClick={() => order.refetch()} loading={order.isFetching}>
            Retry
          </Button>
        }
      >
        {order.error.message}
      </ErrorBanner>
    )
  }

  const data = order.data
  const pending = hasPendingSubOrders(data)

  return (
    <>
      <PageHeader
        title={`Order #${data.id}`}
        description={`Placed by ${data.customer.name} on ${formatDateTime(data.created_at)}.`}
        actions={
          <div className="flex items-center gap-3">
            <StatusBadge status={data.status} />
            <Button variant="secondary" size="sm" onClick={() => order.refetch()} loading={order.isFetching}>
              Refresh
            </Button>
          </div>
        }
      />

      <div className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
        <p className="font-semibold">Thanks, your order is placed.</p>
        <p className="mt-1">
          It was split into {data.sub_orders.length} {data.sub_orders.length === 1 ? 'sub-order' : 'sub-orders'}, one per
          vendor. Each vendor is notified in the background.
        </p>
      </div>

      {pending && <WorkerHint order={data} />}

      <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <section aria-label="Sub-orders" className="space-y-4">
          {data.sub_orders.map((subOrder) => (
            <SubOrderCard key={subOrder.id} subOrder={subOrder} />
          ))}
        </section>

        <aside>
          <Card className="space-y-4 p-5 lg:sticky lg:top-24">
            <h2 className="font-semibold">Order total</h2>
            <TotalsList
              subtotalCents={data.subtotal_cents}
              discountCents={data.discount_cents}
              totalCents={data.total_cents}
            />
            <Link to="/" className="block text-center text-sm font-medium text-indigo-700 hover:underline">
              Continue shopping
            </Link>
          </Card>
        </aside>
      </div>
    </>
  )
}

function WorkerHint({ order }: { order: Order }) {
  const [waitedLong, setWaitedLong] = useState(false)

  useEffect(() => {
    const remainingMs = WORKER_HINT_AFTER_MS - (Date.now() - new Date(order.created_at).getTime())
    const timer = setTimeout(() => setWaitedLong(true), Math.max(0, remainingMs))
    return () => clearTimeout(timer)
  }, [order.created_at])

  if (!waitedLong) {
    return (
      <p className="mb-6 flex items-center gap-2 text-sm text-slate-600">
        <Spinner className="size-3" /> Notifying vendors…
      </p>
    )
  }

  return (
    <div className="mb-6">
      <ErrorBanner tone="warning" title="Vendors haven't been notified yet.">
        Notifications are queued jobs. Make sure <code className="font-mono">php artisan queue:work</code> is running in
        backend/, then press Refresh.
      </ErrorBanner>
    </div>
  )
}

function StatusBadge({ status }: { status: Status }) {
  return status === 'notified' ? (
    <Badge tone="success">Notified</Badge>
  ) : (
    <Badge tone="warning">Pending</Badge>
  )
}

function SubOrderCard({ subOrder }: { subOrder: SubOrder }) {
  return (
    <Card>
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
        <div>
          <h2 className="font-semibold">{subOrder.vendor.name}</h2>
          <p className="text-xs text-slate-500">
            Sub-order #{subOrder.id}
            {subOrder.notified_at && ` · vendor notified ${formatDateTime(subOrder.notified_at)}`}
          </p>
        </div>
        <StatusBadge status={subOrder.status} />
      </div>

      <ul className="divide-y divide-slate-100">
        {subOrder.items.map((item) => (
          <li key={item.id} className="flex items-start justify-between gap-4 px-5 py-3">
            <div className="min-w-0 space-y-1">
              <p className="font-medium text-slate-900">{item.product_name}</p>
              <p className="text-sm text-slate-600">
                {item.quantity} × <Money cents={item.unit_price_cents} />
              </p>
              {item.discount && <Badge tone="accent">{discountLabel(item.discount.rule, item.discount.basis_points)}</Badge>}
            </div>
            <div className="shrink-0 text-right">
              {item.discount_cents > 0 && (
                <Money cents={item.subtotal_cents} className="block text-xs text-slate-400 line-through" />
              )}
              <Money cents={item.total_cents} className="font-semibold" />
            </div>
          </li>
        ))}
      </ul>

      <div className="border-t border-slate-100 bg-slate-50/60 px-5 py-3">
        <TotalsList
          size="sm"
          subtotalCents={subOrder.subtotal_cents}
          discountCents={subOrder.discount_cents}
          totalCents={subOrder.total_cents}
        />
      </div>
    </Card>
  )
}

function OrderNotFound() {
  return (
    <Card className="p-10 text-center">
      <p className="font-semibold text-slate-900">Order not found.</p>
      <Link to="/" className="mt-4 inline-block font-medium text-indigo-700 hover:underline">
        Back to products
      </Link>
    </Card>
  )
}

function OrderSkeleton() {
  return (
    <div className="space-y-4">
      <Skeleton className="h-8 w-48" />
      <Skeleton className="h-16 w-full" />
      <Card className="space-y-3 p-5">
        <Skeleton className="h-5 w-40" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-2/3" />
      </Card>
    </div>
  )
}
