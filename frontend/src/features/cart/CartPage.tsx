import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { UnpriceableProductError, ValidationError } from '../../api/client'
import { useQuote } from '../../api/hooks'
import type { QuotedLine } from '../../api/types'
import { Button, Card, ErrorBanner, Money, PageHeader, Skeleton, Spinner, TotalsList } from '../../components/ui'
import { useCustomer } from '../customer/CustomerContext'
import { CustomersErrorBanner } from '../customer/CustomersErrorBanner'
import { useCart } from './CartContext'
import { MAX_QUANTITY, type CartLine } from './cartReducer'
import { DiscountInfo } from './DiscountInfo'
import { lineErrorsFrom } from './quoteErrors'

export function CartPage() {
  const cart = useCart()
  const customer = useCustomer()
  const quote = useQuote(customer.customerId, cart.items)
  const navigate = useNavigate()

  if (cart.lines.length === 0) {
    return (
      <>
        <PageHeader title="Your cart" />
        <Card className="p-10 text-center">
          <p className="text-slate-600">Your cart is empty.</p>
          <Link to="/" className="mt-4 inline-block font-medium text-indigo-700 hover:underline">
            Browse products
          </Link>
        </Card>
      </>
    )
  }

  const quotedLines = new Map(quote.data?.lines.map((line) => [line.product_id, line]))
  const lineErrors = lineErrorsFrom(quote.error, cart.lines)
  const canReview = quote.data !== undefined && !quote.isUpdating && !quote.isError

  return (
    <>
      <PageHeader
        title="Your cart"
        description="Vendors, prices and discounts are recalculated by the server on every change."
      />

      <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <section aria-label="Cart items" className="space-y-3">
          {customer.error && <CustomersErrorBanner />}
          {quote.isError && <QuoteErrorBanner error={quote.error} onRetry={() => quote.refetch()} />}

          {cart.lines.map((line) => (
            <CartLineRow
              key={line.productId}
              line={line}
              quoted={quotedLines.get(line.productId)}
              stale={quote.isUpdating}
              errors={lineErrors.get(line.productId)}
            />
          ))}
        </section>

        <aside>
          <Card className="space-y-4 p-5 lg:sticky lg:top-24">
            <div className="flex items-center justify-between">
              <h2 className="font-semibold">Summary</h2>
              {quote.isUpdating && !quote.isError && (
                <span className="flex items-center gap-2 text-xs text-slate-500">
                  <Spinner className="size-3" /> Updating
                </span>
              )}
            </div>

            {quote.data ? (
              <div className={quote.isUpdating ? 'opacity-60 transition-opacity' : 'transition-opacity'}>
                <TotalsList
                  subtotalCents={quote.data.subtotal_cents}
                  discountCents={quote.data.discount_cents}
                  totalCents={quote.data.total_cents}
                />
                <p className="mt-3 text-xs text-slate-500">
                  Ships from {quote.data.vendor_groups.length}{' '}
                  {quote.data.vendor_groups.length === 1 ? 'vendor' : 'vendors'}.
                </p>
              </div>
            ) : quote.isError ? (
              <p className="text-sm text-slate-500">Totals are unavailable until the cart can be priced.</p>
            ) : (
              <div className="space-y-2">
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-6 w-full" />
              </div>
            )}

            <Button className="w-full" disabled={!canReview} onClick={() => navigate('/review')}>
              Review order
            </Button>
          </Card>
        </aside>
      </div>
    </>
  )
}

function QuoteErrorBanner({ error, onRetry }: { error: Error; onRetry: () => void }) {
  if (error instanceof UnpriceableProductError) {
    return <ErrorBanner title="A product in your cart can't be priced.">Remove the marked item to continue.</ErrorBanner>
  }
  if (error instanceof ValidationError) {
    const general = error.generalErrors()
    return (
      <ErrorBanner title="Some items need a fix.">{general.length > 0 ? general.join(' ') : 'See the marked items below.'}</ErrorBanner>
    )
  }
  return (
    <ErrorBanner
      title="Couldn't price your cart."
      action={
        <Button variant="secondary" size="sm" onClick={onRetry}>
          Retry
        </Button>
      }
    >
      {error.message}
    </ErrorBanner>
  )
}

function CartLineRow({
  line,
  quoted,
  stale,
  errors,
}: {
  line: CartLine
  quoted: QuotedLine | undefined
  stale: boolean
  errors: string[] | undefined
}) {
  const cart = useCart()

  return (
    <Card className={`p-4 ${errors ? 'border-red-300' : ''}`}>
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
        <div className="min-w-0 flex-1">
          <h3 className="font-medium text-slate-900">{line.name}</h3>
          {quoted ? (
            <div className={`mt-1 space-y-2 text-sm text-slate-600 ${stale ? 'opacity-60' : ''}`}>
              <p>
                <Money cents={quoted.unit_price_cents} /> each · sold by{' '}
                <span className="font-medium text-slate-800">{quoted.vendor.name}</span>
              </p>
              <DiscountInfo line={quoted} />
            </div>
          ) : !errors ? (
            <Skeleton className="mt-2 h-4 w-48" />
          ) : null}
          {errors && (
            <ul className="mt-2 space-y-0.5 text-sm text-red-700">
              {errors.map((message) => (
                <li key={message}>{message}</li>
              ))}
            </ul>
          )}
        </div>

        <div className="flex items-center justify-between gap-4 sm:justify-end">
          <QuantityStepper
            label={line.name}
            quantity={line.quantity}
            onChange={(quantity) => cart.setQuantity(line.productId, quantity)}
          />

          <div className="w-24 text-right">
            {quoted ? (
              <div className={stale ? 'opacity-60' : ''}>
                {quoted.discount_cents > 0 && (
                  <Money cents={quoted.subtotal_cents} className="block text-xs text-slate-400 line-through" />
                )}
                <Money cents={quoted.total_cents} className="font-semibold" />
              </div>
            ) : (
              !errors && <Skeleton className="ml-auto h-5 w-16" />
            )}
          </div>

          <Button variant="ghost" size="sm" onClick={() => cart.remove(line.productId)} aria-label={`Remove ${line.name}`}>
            Remove
          </Button>
        </div>
      </div>
    </Card>
  )
}

function QuantityStepper({ label, quantity, onChange }: { label: string; quantity: number; onChange: (quantity: number) => void }) {
  // What the box shows while the user is typing. It may be empty or not a valid quantity yet ("", "-"),
  // so it can't live in the cart. Valid numbers go to the cart at once; leaving the box drops the draft,
  // and the box shows the cart's quantity again.
  const [draft, setDraft] = useState<string | null>(null)

  const buttonClass =
    'grid size-8 place-items-center text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40'

  return (
    <div className="flex items-center overflow-hidden rounded-lg border border-slate-300 bg-white">
      <button
        type="button"
        className={buttonClass}
        onClick={() => onChange(quantity - 1)}
        disabled={quantity <= 1}
        aria-label={`Decrease quantity of ${label}`}
      >
        −
      </button>
      <input
        type="number"
        inputMode="numeric"
        min={1}
        max={MAX_QUANTITY}
        value={draft ?? quantity}
        onChange={(event) => {
          setDraft(event.target.value)
          const typed = event.target.valueAsNumber
          if (Number.isFinite(typed) && typed >= 1) onChange(typed)
        }}
        onBlur={() => setDraft(null)}
        aria-label={`Quantity of ${label}`}
        // The ring is drawn inside the box (negative offset): the stepper's overflow-hidden would clip one outside it.
        className="h-8 w-12 border-x border-slate-300 text-center text-sm tabular-nums [appearance:textfield] focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 [&::-webkit-inner-spin-button]:appearance-none"
      />
      <button
        type="button"
        className={buttonClass}
        onClick={() => onChange(quantity + 1)}
        disabled={quantity >= MAX_QUANTITY}
        aria-label={`Increase quantity of ${label}`}
      >
        +
      </button>
    </div>
  )
}
