import type { ReactNode } from 'react'
import { Link } from 'react-router'
import { useProducts } from '../../api/hooks'
import type { Product } from '../../api/types'
import { Badge, Button, Card, ErrorBanner, Money, PageHeader, Skeleton } from '../../components/ui'
import { useCart } from '../cart/CartContext'

export function ProductsPage() {
  const products = useProducts()

  return (
    <>
      <PageHeader
        title="Products"
        description="Each product can be sold by several vendors. Your cart always uses the lowest active price."
      />

      {products.isPending ? (
        <ProductGrid>
          {Array.from({ length: 6 }, (_, i) => (
            <ProductCardSkeleton key={i} />
          ))}
        </ProductGrid>
      ) : products.isError ? (
        <ErrorBanner
          title="Couldn't load products."
          action={
            <Button variant="secondary" size="sm" onClick={() => products.refetch()} loading={products.isFetching}>
              Retry
            </Button>
          }
        >
          {products.error.message}
        </ErrorBanner>
      ) : products.data.length === 0 ? (
        <Card className="p-8 text-center text-slate-600">No products yet. Run `php artisan db:seed` in backend/.</Card>
      ) : (
        <ProductGrid>
          {products.data.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </ProductGrid>
      )}
    </>
  )
}

function ProductGrid({ children }: { children: ReactNode }) {
  return <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{children}</div>
}

function ProductCard({ product }: { product: Product }) {
  const cart = useCart()
  const best = product.best_offer
  const inCart = cart.lines.find((line) => line.productId === product.id)?.quantity ?? 0
  const otherOffers = product.offers.filter((offer) => offer.id !== best?.id)

  return (
    <Card className="flex flex-col p-5">
      <div className="flex items-start justify-between gap-2">
        <Badge>{product.category.name}</Badge>
        <span className="font-mono text-xs text-slate-400">{product.sku}</span>
      </div>

      <h2 className="mt-3 text-base font-semibold text-slate-900">{product.name}</h2>

      {best ? (
        <div className="mt-3">
          <div className="flex items-baseline gap-2">
            <Money cents={best.price_cents} className="text-2xl font-semibold" />
            <Badge tone="success">Best price</Badge>
          </div>
          <p className="mt-1 text-sm text-slate-600">from {best.vendor.name}</p>
        </div>
      ) : (
        <p className="mt-3 text-sm text-slate-500">Currently unavailable from any vendor.</p>
      )}

      {otherOffers.length > 0 && (
        <ul className="mt-3 space-y-0.5 border-t border-slate-100 pt-3 text-xs text-slate-500">
          {otherOffers.map((offer) => (
            <li key={offer.id} className="flex justify-between">
              <span>{offer.vendor.name}</span>
              <Money cents={offer.price_cents} />
            </li>
          ))}
        </ul>
      )}

      <div className="mt-auto flex items-center gap-3 pt-5">
        <Button
          className="flex-1"
          disabled={!best || (cart.isFull && inCart === 0)}
          onClick={() => cart.add(product.id, product.name)}
        >
          {inCart > 0 ? 'Add one more' : 'Add to cart'}
        </Button>
        {inCart > 0 && (
          <Link to="/cart" className="text-sm font-medium text-indigo-700 hover:underline">
            {inCart} in cart
          </Link>
        )}
      </div>
    </Card>
  )
}

function ProductCardSkeleton() {
  return (
    <Card className="p-5">
      <Skeleton className="h-5 w-24" />
      <Skeleton className="mt-4 h-5 w-3/4" />
      <Skeleton className="mt-4 h-8 w-28" />
      <Skeleton className="mt-2 h-4 w-32" />
      <Skeleton className="mt-6 h-10 w-full" />
    </Card>
  )
}
