import { Link, NavLink, Outlet, useLocation } from 'react-router'
import { useCart } from '../features/cart/CartContext'
import { CustomerSelect } from '../features/customer/CustomerSelect'
import { ErrorBoundary } from './ErrorBoundary'

const navLinkClass = ({ isActive }: { isActive: boolean }) =>
  `rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
    isActive ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
  }`

export function Layout() {
  const { count } = useCart()
  const { pathname } = useLocation()

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900">
      <header className="sticky top-0 z-10 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3">
          <Link to="/" className="flex items-center gap-2 font-semibold tracking-tight">
            <span aria-hidden className="grid size-7 place-items-center rounded-lg bg-indigo-600 text-sm text-white">
              M
            </span>
            Multi-Vendor Shop
          </Link>

          <nav className="flex items-center gap-1" aria-label="Main">
            <NavLink to="/" end className={navLinkClass}>
              Products
            </NavLink>
            <NavLink to="/cart" className={navLinkClass}>
              <span className="flex items-center gap-2">
                Cart
                <span
                  className={`min-w-5 rounded-full px-1.5 text-center text-xs font-semibold tabular-nums ${
                    count > 0 ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-600'
                  }`}
                  aria-label={`${count} items in cart`}
                >
                  {count}
                </span>
              </span>
            </NavLink>
          </nav>

          <div className="w-full sm:w-auto">
            <CustomerSelect />
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-4 py-8">
        {/* Keyed by path: a render error on one page is cleared by navigating to another. */}
        <ErrorBoundary key={pathname}>
          <Outlet />
        </ErrorBoundary>
      </main>
    </div>
  )
}
