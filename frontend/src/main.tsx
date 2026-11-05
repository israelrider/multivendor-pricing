import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router'
import { ApiError } from './api/client'
import App from './App.tsx'
import { CartProvider } from './features/cart/CartProvider'
import { CustomerProvider } from './features/customer/CustomerProvider'
import './index.css'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // Retry once for network or server hiccups. A 4xx (validation, not found) will fail again.
      retry: (failureCount, error) => !(error instanceof ApiError && error.isClientError) && failureCount < 1,
      refetchOnWindowFocus: false,
    },
  },
})

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <CustomerProvider>
          <CartProvider>
            <App />
          </CartProvider>
        </CustomerProvider>
      </BrowserRouter>
    </QueryClientProvider>
  </StrictMode>,
)
