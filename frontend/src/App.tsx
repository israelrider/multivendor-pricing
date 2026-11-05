import { Link, Route, Routes } from 'react-router'
import { Layout } from './components/Layout'
import { Card } from './components/ui'
import { CartPage } from './features/cart/CartPage'
import { OrderPage } from './features/order/OrderPage'
import { ProductsPage } from './features/products/ProductsPage'
import { ReviewPage } from './features/review/ReviewPage'

// Products → Cart → Review → Result (README section 7).
export default function App() {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route index element={<ProductsPage />} />
        <Route path="cart" element={<CartPage />} />
        <Route path="review" element={<ReviewPage />} />
        <Route path="orders/:id" element={<OrderPage />} />
        <Route path="*" element={<NotFound />} />
      </Route>
    </Routes>
  )
}

function NotFound() {
  return (
    <Card className="p-10 text-center">
      <p className="font-semibold">Page not found.</p>
      <Link to="/" className="mt-4 inline-block font-medium text-indigo-700 hover:underline">
        Back to products
      </Link>
    </Card>
  )
}
