import { useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router'
import { QuoteChangedError, UnpriceableProductError, ValidationError } from '../../api/client'
import { queryKeys, useCreateOrder, useQuote } from '../../api/hooks'
import type { QuotedLine, VendorGroup } from '../../api/types'
import { Badge, Button, Card, ErrorBanner, Money, PageHeader, Skeleton, Spinner, TotalsList } from '../../components/ui'
import { formatCents } from '../../lib/format'
import { useCart } from '../cart/CartContext'
import { DiscountInfo } from '../cart/DiscountInfo'
import { lineErrorsFrom } from '../cart/quoteErrors'
import { useCustomer } from '../customer/CustomerContext'
import { CustomersErrorBanner } from '../customer/CustomersErrorBanner'

export function ReviewPage() {
  const cart = useCart()
  const customer = useCustomer()
  const quote = useQuote(customer.customerId, cart.items)
  const createOrder = useCreateOrder()
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  if (cart.lines.length === 0) {
    return (
      <>
        <PageHeader title="Review your order" />
        <Card className="p-10 text-center">
          <p className="text-slate-600">There's nothing to review yet.</p>
          <Link to="/" className="mt-4 inline-block font-medium text-indigo-700 hover:underline">
            Browse products
          </Link>
        </Card>
      </>
    )
  }

  const submit = () => {
    if (!quote.data || customer.customerId === null) return
    const customerId = customer.customerId
    const items = cart.items

    createOrder.mutate(
      // The reviewed total goes along, so the server refuses (409) if prices moved meanwhile.
      { customer_id: customerId, items, expected_total_cents: quote.data.total_cents },
      {
        onSuccess: (order) => {
          navigate(`/orders/${order.id}`)
          cart.clear()
        },
        onError: (error) => {
          if (error instanceof QuoteChangedError) {
            // Show the fresh prices right away; the next submit sends the new total.
            queryClient.setQueryData(queryKeys.quote(customerId, items), error.quote)
          } else if (error instanceof UnpriceableProductError) {
            void queryClient.invalidateQueries({ queryKey: queryKeys.products })
          }
        },
      },
    )
  }

  const lineErrors = lineErrorsFrom(createOrder.error ?? quote.error, cart.lines)
  const canSubmit = quote.data !== undefined && !quote.isUpdating && !quote.isError && customer.customerId !== null

  return (
    <>
      <PageHeader
        title="Review your order"
        description="Your order is split into one sub-order per vendor. Each vendor is notified separately."
        actions={
          customer.customer && (
            <p className="flex items-center gap-2 text-sm text-slate-600">
              Ordering as <span className="font-medium text-slate-900">{customer.customer.name}</span>
              {customer.customer.is_loyal && <Badge tone="accent">Loyal customer</Badge>}
            </p>
          )
        }
      />

      <div className="space-y-4">
        {customer.error && <CustomersErrorBanner />}
        {createOrder.error ? (
          <SubmitErrorBanner error={createOrder.error} />
        ) : (
          quote.isError && (
            <ErrorBanner
              title="Couldn't price your cart."
              action={
                <Link to="/cart" className="font-medium underline">
                  Back to cart
                </Link>
              }
            >
              {quote.error.message}
            </ErrorBanner>
          )
        )}
      </div>

      <div className="mt-4 grid gap-6 lg:grid-cols-[1fr_20rem]">
        <section aria-label="Sub-orders by vendor" className="space-y-4">
          {quote.data
            ? quote.data.vendor_groups.map((group, index) => (
                <VendorGroupCard
                  key={group.vendor.id}
                  group={group}
                  position={`${index + 1} of ${quote.data.vendor_groups.length}`}
                  stale={quote.isUpdating}
                  lineErrors={lineErrors}
                />
              ))
            : !quote.isError && <VendorGroupSkeleton />}
        </section>

        <aside>
          <Card className="space-y-4 p-5 lg:sticky lg:top-24">
            <div className="flex items-center justify-between">
              <h2 className="font-semibold">Order total</h2>
              {quote.isUpdating && !quote.isError && <Spinner className="size-3 text-slate-500" />}
            </div>

            {quote.data ? (
              <TotalsList
                subtotalCents={quote.data.subtotal_cents}
                discountCents={quote.data.discount_cents}
                totalCents={quote.data.total_cents}
              />
            ) : (
              <Skeleton className="h-20 w-full" />
            )}

            <Button className="w-full" onClick={submit} disabled={!canSubmit} loading={createOrder.isPending}>
              {createOrder.isPending ? 'Placing order…' : 'Place order'}
            </Button>
            <Link to="/cart" className="block text-center text-sm text-slate-600 hover:text-slate-900 hover:underline">
              Back to cart
            </Link>
          </Card>
        </aside>
      </div>
    </>
  )
}

function SubmitErrorBanner({ error }: { error: Error }) {
  if (error instanceof QuoteChangedError) {
    return (
      <ErrorBanner tone="warning" title="Prices changed while you were reviewing.">
        You reviewed a total of {formatCents(error.expectedTotalCents)}; it's now {formatCents(error.quote.total_cents)}. No
        order was placed. Please check the updated order below and place it again.
      </ErrorBanner>
    )
  }
  if (error instanceof UnpriceableProductError) {
    return (
      <ErrorBanner
        title="A product is no longer available."
        action={
          <Link to="/cart" className="font-medium underline">
            Back to cart
          </Link>
        }
      >
        No order was placed. Remove the marked item from your cart and try again.
      </ErrorBanner>
    )
  }
  if (error instanceof ValidationError) {
    const general = error.generalErrors()
    return (
      <ErrorBanner title="The order couldn't be placed.">
        {general.length > 0 ? general.join(' ') : 'See the marked items below.'}
      </ErrorBanner>
    )
  }
  return (
    <ErrorBanner title="The order couldn't be placed.">
      {error.message} Your cart is unchanged, so you can try again.
    </ErrorBanner>
  )
}

function VendorGroupCard({
  group,
  position,
  stale,
  lineErrors,
}: {
  group: VendorGroup
  position: string
  stale: boolean
  lineErrors: Map<number, string[]>
}) {
  return (
    <Card className={stale ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
      <div className="flex items-center justify-between border-b border-slate-100 px-5 py-3">
        <h2 className="font-semibold">{group.vendor.name}</h2>
        <Badge>Sub-order {position}</Badge>
      </div>

      <ul className="divide-y divide-slate-100">
        {group.lines.map((line) => (
          <ReviewLine key={line.product_id} line={line} errors={lineErrors.get(line.product_id)} />
        ))}
      </ul>

      <div className="border-t border-slate-100 bg-slate-50/60 px-5 py-3">
        <TotalsList
          size="sm"
          subtotalCents={group.subtotal_cents}
          discountCents={group.discount_cents}
          totalCents={group.total_cents}
        />
      </div>
    </Card>
  )
}

function ReviewLine({ line, errors }: { line: QuotedLine; errors: string[] | undefined }) {
  return (
    <li className="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
      <div className="min-w-0 space-y-1.5">
        <p className="font-medium text-slate-900">{line.product_name}</p>
        <p className="text-sm text-slate-600">
          {line.quantity} × <Money cents={line.unit_price_cents} />
        </p>
        <DiscountInfo line={line} />
        {errors && (
          <ul className="text-sm text-red-700">
            {errors.map((message) => (
              <li key={message}>{message}</li>
            ))}
          </ul>
        )}
      </div>
      <div className="shrink-0 text-right">
        {line.discount_cents > 0 && (
          <Money cents={line.subtotal_cents} className="block text-xs text-slate-400 line-through" />
        )}
        <Money cents={line.total_cents} className="font-semibold" />
      </div>
    </li>
  )
}

function VendorGroupSkeleton() {
  return (
    <Card className="space-y-3 p-5">
      <Skeleton className="h-5 w-40" />
      <Skeleton className="h-4 w-full" />
      <Skeleton className="h-4 w-2/3" />
    </Card>
  )
}
