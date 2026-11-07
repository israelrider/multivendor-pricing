import { createContext, useContext } from 'react'
import type { Customer } from '../../api/types'

/**
 * Who the shop is "acting as". There's no auth (README, "Assumptions"), so the customer is picked
 * from the seeded demo customers. It's global because the quote depends on it: loyal customers
 * get the loyalty discount already in the cart.
 */
export type CustomerContextValue = {
  customers: Customer[]
  customer: Customer | null
  /** null until the customer list has loaded. */
  customerId: number | null
  setCustomerId: (id: number) => void
  isLoading: boolean
  error: Error | null
  retry: () => void
}

export const CustomerContext = createContext<CustomerContextValue | null>(null)

export function useCustomer(): CustomerContextValue {
  const value = useContext(CustomerContext)
  if (!value) throw new Error('useCustomer must be used inside <CustomerProvider>')
  return value
}
