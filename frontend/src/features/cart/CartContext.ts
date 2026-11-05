import { createContext, useContext } from 'react'
import type { CartItemInput } from '../../api/types'
import type { CartLine } from './cartReducer'

export type CartContextValue = {
  lines: CartLine[]
  /** The cart in the API's request shape, in cart order. */
  items: CartItemInput[]
  /** Total units, for the header badge. */
  count: number
  isFull: boolean
  add: (productId: number, name: string) => void
  setQuantity: (productId: number, quantity: number) => void
  remove: (productId: number) => void
  clear: () => void
}

export const CartContext = createContext<CartContextValue | null>(null)

export function useCart(): CartContextValue {
  const value = useContext(CartContext)
  if (!value) throw new Error('useCart must be used inside <CartProvider>')
  return value
}
