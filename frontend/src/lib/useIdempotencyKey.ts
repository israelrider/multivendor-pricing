import { useCallback, useRef } from 'react'

/**
 * One idempotency key per distinct request. `keyFor(scope)` returns the same key for as long as
 * `scope` (anything JSON-serializable, e.g. the customer and cart) stays the same, and a new one
 * once it changes. So a double click or a retry after a network error reuses the key, and the server
 * returns the order it already placed instead of creating a second one. Remounting the component
 * (a new checkout) starts with a fresh key.
 */
export function useIdempotencyKey() {
  const current = useRef<{ scope: string; key: string } | null>(null)

  return useCallback((scope: unknown): string => {
    const serialized = JSON.stringify(scope)
    if (current.current?.scope !== serialized) {
      current.current = { scope: serialized, key: randomKey() }
    }
    return current.current.key
  }, [])
}

/** `crypto.randomUUID` needs a secure context (https or localhost); the fallback works on plain http too. */
function randomKey(): string {
  if (typeof crypto.randomUUID === 'function') return crypto.randomUUID()
  return Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join('')
}
