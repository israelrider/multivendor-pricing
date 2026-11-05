import { Button, ErrorBanner } from '../../components/ui'
import { useCustomer } from './CustomerContext'

/** Quotes need a customer, so without the customer list nothing can be priced. */
export function CustomersErrorBanner() {
  const { error, retry } = useCustomer()
  return (
    <ErrorBanner
      title="Couldn't load customers, so the cart can't be priced."
      action={
        <Button variant="secondary" size="sm" onClick={retry}>
          Retry
        </Button>
      }
    >
      {error?.message}
    </ErrorBanner>
  )
}
