import { useId } from 'react'
import { Badge, Skeleton } from '../../components/ui'
import { useCustomer } from './CustomerContext'

export function CustomerSelect({ compact = false }: { compact?: boolean }) {
  const id = useId()
  const { customers, customer, setCustomerId, isLoading, error } = useCustomer()

  if (isLoading) return <Skeleton className="h-9 w-44" />
  if (error) return <span className="text-sm text-red-700">Customers unavailable</span>

  return (
    <div className="flex items-center gap-2">
      <label htmlFor={id} className={compact ? 'sr-only' : 'text-sm font-medium text-slate-700'}>
        Shopping as
      </label>
      <select
        id={id}
        value={customer?.id ?? ''}
        onChange={(event) => setCustomerId(Number(event.target.value))}
        className="h-9 rounded-lg border border-slate-300 bg-white px-2 text-sm text-slate-800 focus-visible:outline-2 focus-visible:outline-indigo-600"
      >
        {customers.map((c) => (
          <option key={c.id} value={c.id}>
            {c.name}
            {c.is_loyal ? ' (loyal)' : ''}
          </option>
        ))}
      </select>
      {!compact && customer?.is_loyal && <Badge tone="accent">Loyal customer</Badge>}
    </div>
  )
}
