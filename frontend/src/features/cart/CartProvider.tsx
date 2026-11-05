import { useEffect, useMemo, useReducer, type ReactNode } from 'react'
import { CartContext, type CartContextValue } from './CartContext'
import { cartReducer, loadCart, MAX_CART_LINES, saveCart, toCartItems } from './cartReducer'

export function CartProvider({ children }: { children: ReactNode }) {
  const [lines, dispatch] = useReducer(cartReducer, undefined, loadCart)

  useEffect(() => saveCart(lines), [lines])

  const value = useMemo<CartContextValue>(
    () => ({
      lines,
      items: toCartItems(lines),
      count: lines.reduce((sum, line) => sum + line.quantity, 0),
      isFull: lines.length >= MAX_CART_LINES,
      add: (productId, name) => dispatch({ type: 'add', productId, name }),
      setQuantity: (productId, quantity) => dispatch({ type: 'setQuantity', productId, quantity }),
      remove: (productId) => dispatch({ type: 'remove', productId }),
      clear: () => dispatch({ type: 'clear' }),
    }),
    [lines],
  )

  return <CartContext value={value}>{children}</CartContext>
}
