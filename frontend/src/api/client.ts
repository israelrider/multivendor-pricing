import type { Quote } from './types'

/**
 * Every failed request becomes an ApiError. The subclasses mirror the error shapes the
 * backend documents (bootstrap/app.php), so screens can `instanceof` them instead of
 * poking at status codes and raw JSON.
 */
export class ApiError extends Error {
  readonly status: number

  constructor(message: string, status: number) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }

  /** 4xx: retrying the same request won't help. */
  get isClientError(): boolean {
    return this.status >= 400 && this.status < 500
  }
}

/** 422 from a FormRequest: `{message, errors: {"items.1.product_id": ["..."]}}`. */
export class ValidationError extends ApiError {
  readonly errors: Record<string, string[]>

  constructor(message: string, errors: Record<string, string[]>) {
    super(message, 422)
    this.name = 'ValidationError'
    this.errors = errors
  }

  /** Messages for `items.<index>.*` keys, grouped by cart index. */
  itemErrors(): Map<number, string[]> {
    const byIndex = new Map<number, string[]>()
    for (const [key, messages] of Object.entries(this.errors)) {
      const match = /^items\.(\d+)\./.exec(key)
      if (!match) continue
      const index = Number(match[1])
      byIndex.set(index, [...(byIndex.get(index) ?? []), ...messages])
    }
    return byIndex
  }

  /** Messages that don't belong to one cart line (customer_id, items, ...). */
  generalErrors(): string[] {
    return Object.entries(this.errors)
      .filter(([key]) => !/^items\.\d+\./.test(key))
      .flatMap(([, messages]) => messages)
  }
}

/** 422 when a product in the cart has no active offer (any more): `{message, product_id}`. */
export class UnpriceableProductError extends ApiError {
  readonly productId: number

  constructor(message: string, productId: number) {
    super(message, 422)
    this.name = 'UnpriceableProductError'
    this.productId = productId
  }
}

/** 409 from POST /orders: the reviewed total is stale. Carries the fresh quote. */
export class QuoteChangedError extends ApiError {
  readonly expectedTotalCents: number
  readonly quote: Quote

  constructor(message: string, expectedTotalCents: number, quote: Quote) {
    super(message, 409)
    this.name = 'QuoteChangedError'
    this.expectedTotalCents = expectedTotalCents
    this.quote = quote
  }
}

const UNREACHABLE = 'Could not reach the API. Is `php artisan serve` running on port 8000?'

type ErrorBody = {
  message?: string
  errors?: Record<string, string[]>
  product_id?: number
  expected_total_cents?: number
  quote?: Quote
}

function toError(status: number, body: ErrorBody | null): ApiError {
  const message = body?.message || `Request failed (HTTP ${status})`

  if (status === 422 && body?.errors) return new ValidationError(message, body.errors)
  if (status === 422 && typeof body?.product_id === 'number') {
    return new UnpriceableProductError(message, body.product_id)
  }
  if (status === 409 && body?.quote && typeof body.expected_total_cents === 'number') {
    return new QuoteChangedError(message, body.expected_total_cents, body.quote)
  }
  if (status === 404) return new ApiError('Not found.', 404)
  // No JSON at all: in dev that's the Vite proxy failing to reach Laravel.
  if (status >= 500 && body === null) return new ApiError(UNREACHABLE, status)
  if (status >= 500) return new ApiError('The server had a problem. Please try again.', status)

  return new ApiError(message, status)
}

type RequestInit = { method?: string; body?: unknown; headers?: Record<string, string>; signal?: AbortSignal }

/** Fetches `/api{path}` and returns the resource's `data`. */
async function request<T>(path: string, init?: RequestInit): Promise<T> {
  let response: Response
  try {
    response = await fetch(`/api${path}`, {
      method: init?.method ?? 'GET',
      headers: {
        Accept: 'application/json',
        ...(init?.body !== undefined && { 'Content-Type': 'application/json' }),
        ...init?.headers,
      },
      body: init?.body === undefined ? undefined : JSON.stringify(init.body),
      signal: init?.signal,
    })
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') throw error
    // status 0: the request never got an HTTP response (server down, proxy error, offline).
    throw new ApiError(UNREACHABLE, 0)
  }

  const body = (await response.json().catch(() => null)) as unknown

  if (!response.ok) throw toError(response.status, body as ErrorBody | null)

  return (body as { data: T }).data
}

export const api = {
  get: <T>(path: string, signal?: AbortSignal) => request<T>(path, { signal }),
  post: <T>(path: string, body: unknown, options?: { headers?: Record<string, string>; signal?: AbortSignal }) =>
    request<T>(path, { method: 'POST', body, ...options }),
}
