import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useDebouncedValue } from '../lib/useDebouncedValue'
import { api } from './client'
import type { CartItemInput, Customer, Order, OrderRequest, Product, Quote } from './types'

export const queryKeys = {
  products: ['products'] as const,
  customers: ['customers'] as const,
  quote: (customerId: number, items: CartItemInput[]) => ['quote', customerId, items] as const,
  order: (id: number) => ['orders', id] as const,
}

// Plain reads don't pass TanStack's abort `signal` to fetch. Without it, a remount (StrictMode in dev,
// quick navigation) reuses the request in flight instead of aborting it and sending a new one.
// Only the quote passes the signal, because an outdated quote is worth aborting.

export function useProducts() {
  return useQuery({
    queryKey: queryKeys.products,
    queryFn: () => api.get<Product[]>('/products'),
  })
}

export function useCustomers() {
  return useQuery({
    queryKey: queryKeys.customers,
    queryFn: () => api.get<Customer[]>('/customers'),
    staleTime: Infinity,
  })
}

const QUOTE_DEBOUNCE_MS = 300

/**
 * Prices the cart on the server. Rapid quantity clicks are debounced into one request, and the
 * previous quote stays on screen (`isPlaceholderData`) while the next one loads.
 */
export function useQuote(customerId: number | null, items: CartItemInput[]) {
  // Debounce a string so a new-but-equal items array doesn't restart the timer.
  const itemsJson = useDebouncedValue(JSON.stringify(items), QUOTE_DEBOUNCE_MS)
  const debouncedItems = JSON.parse(itemsJson) as CartItemInput[]
  const isDebouncing = itemsJson !== JSON.stringify(items)

  const query = useQuery({
    queryKey: queryKeys.quote(customerId ?? 0, debouncedItems),
    queryFn: ({ signal }) =>
      api.post<Quote>('/cart/quote', { customer_id: customerId, items: debouncedItems }, { signal }),
    enabled: customerId !== null && debouncedItems.length > 0,
    placeholderData: keepPreviousData,
  })

  return {
    ...query,
    /** True while the shown quote doesn't match the cart yet (debounce, request in flight, or old data). */
    isUpdating: isDebouncing || query.isFetching || query.isPlaceholderData,
  }
}

/**
 * Places an order. The idempotency key goes in the `Idempotency-Key` header: sending the same key and
 * cart again returns the order already placed (HTTP 200) instead of creating a second one.
 */
export function useCreateOrder() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ order, idempotencyKey }: { order: OrderRequest; idempotencyKey: string }) =>
      api.post<Order>('/orders', order, { headers: { 'Idempotency-Key': idempotencyKey } }),
    onSuccess: (order) => {
      queryClient.setQueryData(queryKeys.order(order.id), order)
    },
  })
}

const ORDER_POLL_MS = 2000
/** Stop polling after about a minute; the worker probably isn't running. The page offers a manual refresh. */
const ORDER_MAX_POLLS = 30

export function hasPendingSubOrders(order: Order): boolean {
  return order.sub_orders.some((subOrder) => subOrder.status === 'pending')
}

/** Loads an order and polls it while vendor notifications are still queued. */
export function useOrder(id: number) {
  return useQuery({
    queryKey: queryKeys.order(id),
    queryFn: () => api.get<Order>(`/orders/${id}`),
    refetchInterval: (query) => {
      const order = query.state.data
      if (!order || !hasPendingSubOrders(order)) return false
      return query.state.dataUpdateCount < ORDER_MAX_POLLS ? ORDER_POLL_MS : false
    },
  })
}
