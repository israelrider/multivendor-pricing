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

export function useProducts() {
  return useQuery({
    queryKey: queryKeys.products,
    queryFn: ({ signal }) => api.get<Product[]>('/products', signal),
  })
}

export function useCustomers() {
  return useQuery({
    queryKey: queryKeys.customers,
    queryFn: ({ signal }) => api.get<Customer[]>('/customers', signal),
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
      api.post<Quote>('/cart/quote', { customer_id: customerId, items: debouncedItems }, signal),
    enabled: customerId !== null && debouncedItems.length > 0,
    placeholderData: keepPreviousData,
  })

  return {
    ...query,
    /** True while the shown quote doesn't match the cart yet (debounce, request in flight, or old data). */
    isUpdating: isDebouncing || query.isFetching || query.isPlaceholderData,
  }
}

export function useCreateOrder() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (input: OrderRequest) => api.post<Order>('/orders', input),
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
    queryFn: ({ signal }) => api.get<Order>(`/orders/${id}`, signal),
    refetchInterval: (query) => {
      const order = query.state.data
      if (!order || !hasPendingSubOrders(order)) return false
      return query.state.dataUpdateCount < ORDER_MAX_POLLS ? ORDER_POLL_MS : false
    },
  })
}
