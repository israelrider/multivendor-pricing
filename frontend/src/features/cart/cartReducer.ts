import type { CartItemInput } from '../../api/types'

/** Same limits as the backend's QuoteCartRequest. */
export const MAX_CART_LINES = 50
export const MAX_QUANTITY = 999

/**
 * The cart only stores what the customer chose. Prices, vendors and discounts always come
 * from the server quote; the name is kept so a line can still be shown if pricing fails.
 */
export type CartLine = { productId: number; name: string; quantity: number }

export type CartAction =
  | { type: 'add'; productId: number; name: string }
  | { type: 'setQuantity'; productId: number; quantity: number }
  | { type: 'remove'; productId: number }
  | { type: 'clear' }

const clampQuantity = (quantity: number) => Math.min(MAX_QUANTITY, Math.max(1, Math.trunc(quantity)))

export function cartReducer(lines: CartLine[], action: CartAction): CartLine[] {
  switch (action.type) {
    case 'add': {
      // Adding a product that's already in the cart bumps its quantity: one line per product.
      const existing = lines.find((line) => line.productId === action.productId)
      if (existing) {
        return lines.map((line) =>
          line === existing ? { ...line, quantity: clampQuantity(line.quantity + 1) } : line,
        )
      }
      if (lines.length >= MAX_CART_LINES) return lines
      return [...lines, { productId: action.productId, name: action.name, quantity: 1 }]
    }
    case 'setQuantity':
      if (!Number.isFinite(action.quantity)) return lines
      return lines.map((line) =>
        line.productId === action.productId ? { ...line, quantity: clampQuantity(action.quantity) } : line,
      )
    case 'remove':
      return lines.filter((line) => line.productId !== action.productId)
    case 'clear':
      return []
  }
}

export function toCartItems(lines: CartLine[]): CartItemInput[] {
  return lines.map((line) => ({ product_id: line.productId, quantity: line.quantity }))
}

const STORAGE_KEY = 'mvp.cart.v1'

function isCartLine(value: unknown): value is CartLine {
  if (typeof value !== 'object' || value === null) return false
  const line = value as Record<string, unknown>
  return Number.isInteger(line.productId) && typeof line.name === 'string' && Number.isInteger(line.quantity)
}

/** Reads the saved cart. Anything unreadable or malformed means an empty cart, never a crash. */
export function loadCart(): CartLine[] {
  try {
    const parsed: unknown = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]')
    if (!Array.isArray(parsed)) return []
    const seen = new Set<number>()
    return parsed
      .filter(isCartLine)
      .filter((line) => !seen.has(line.productId) && seen.add(line.productId))
      .slice(0, MAX_CART_LINES)
      .map((line) => ({ ...line, quantity: clampQuantity(line.quantity) }))
  } catch {
    return []
  }
}

export function saveCart(lines: CartLine[]): void {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(lines))
  } catch {
    // Storage full or blocked (private mode): the cart still works for this tab.
  }
}
