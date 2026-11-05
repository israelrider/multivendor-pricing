import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { useCustomers } from '../../api/hooks'
import { CustomerContext, type CustomerContextValue } from './CustomerContext'

const STORAGE_KEY = 'mvp.customerId.v1'

function loadCustomerId(): number | null {
  try {
    const id = Number(localStorage.getItem(STORAGE_KEY))
    return Number.isInteger(id) && id > 0 ? id : null
  } catch {
    return null
  }
}

export function CustomerProvider({ children }: { children: ReactNode }) {
  const { data, isPending, error, refetch } = useCustomers()
  const [chosenId, setChosenId] = useState(loadCustomerId)

  useEffect(() => {
    try {
      if (chosenId !== null) localStorage.setItem(STORAGE_KEY, String(chosenId))
    } catch {
      // Not persisted; the choice still holds for this tab.
    }
  }, [chosenId])

  const value = useMemo<CustomerContextValue>(() => {
    const customers = data ?? []
    // A saved id that no longer exists (reseeded DB) falls back to the first demo customer.
    const customer = customers.find((c) => c.id === chosenId) ?? customers[0] ?? null

    return {
      customers,
      customer,
      customerId: customer?.id ?? null,
      setCustomerId: setChosenId,
      isLoading: isPending,
      error,
      retry: () => void refetch(),
    }
  }, [data, isPending, error, refetch, chosenId])

  return <CustomerContext value={value}>{children}</CustomerContext>
}
