// Response shapes of the Laravel API (backend/app/Http/Resources). Money is always integer cents,
// percentages are integer basis points (100 bps = 1%).

export type VendorRef = { id: number; name: string }

export type Offer = {
  id: number
  vendor: VendorRef
  price_cents: number
}

export type Product = {
  id: number
  name: string
  sku: string
  category: { id: number; name: string; slug: string }
  /** Active offers, cheapest first. */
  offers: Offer[]
  /** The offer the cart will use: lowest price, ties go to the lowest vendor id. */
  best_offer: Offer | null
}

export type Customer = {
  id: number
  name: string
  email: string
  is_loyal: boolean
}

export type DiscountRule = 'quantity' | 'category' | 'loyalty' | (string & {})

export type Discount = {
  rule: DiscountRule
  label: string
  basis_points: number
}

export type QuotedLine = {
  product_id: number
  product_name: string
  sku: string
  category: { slug: string; name: string }
  vendor: VendorRef
  unit_price_cents: number
  quantity: number
  subtotal_cents: number
  /** The one discount applied to this line (best discount wins), or null. */
  applied_discount: Discount | null
  /** Every discount the line qualifies for, including the applied one. */
  applicable_discounts: (Discount & { applied: boolean })[]
  discount_cents: number
  total_cents: number
}

export type Totals = {
  subtotal_cents: number
  discount_cents: number
  total_cents: number
}

export type VendorGroup = Totals & {
  vendor: VendorRef
  lines: QuotedLine[]
}

export type Quote = Totals & {
  customer_id: number
  /** In cart order. */
  lines: QuotedLine[]
  vendor_groups: VendorGroup[]
}

export type Status = 'pending' | 'notified'

export type OrderItem = {
  id: number
  product_id: number
  product_name: string
  quantity: number
  unit_price_cents: number
  subtotal_cents: number
  /** Only the rule and percentage are stored, so the UI builds the label. */
  discount: { rule: DiscountRule; basis_points: number } | null
  discount_cents: number
  total_cents: number
}

export type SubOrder = Totals & {
  id: number
  vendor: VendorRef
  status: Status
  notified_at: string | null
  items: OrderItem[]
}

export type Order = Totals & {
  id: number
  customer: { id: number; name: string }
  status: Status
  sub_orders: SubOrder[]
  created_at: string
}

export type CartItemInput = { product_id: number; quantity: number }

export type QuoteRequest = {
  customer_id: number
  items: CartItemInput[]
}

export type OrderRequest = QuoteRequest & {
  /** The total the customer reviewed. A different server total gives 409 with a fresh quote. */
  expected_total_cents?: number
}
