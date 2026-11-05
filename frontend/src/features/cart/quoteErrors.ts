import { UnpriceableProductError, ValidationError } from '../../api/client'
import type { CartLine } from './cartReducer'

/**
 * Turns a failed quote or order request into messages per cart product, so they can be shown
 * on the line itself. Validation keys use the cart index (`items.1.quantity`); the unpriceable
 * error names the product directly.
 */
export function lineErrorsFrom(error: unknown, lines: CartLine[]): Map<number, string[]> {
  const byProduct = new Map<number, string[]>()

  if (error instanceof ValidationError) {
    for (const [index, messages] of error.itemErrors()) {
      const line = lines[index]
      if (line) byProduct.set(line.productId, messages)
    }
  } else if (error instanceof UnpriceableProductError) {
    byProduct.set(error.productId, ['No vendor sells this product right now. Please remove it from your cart.'])
  }

  return byProduct
}
