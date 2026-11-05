import type { DiscountRule } from '../api/types'

const usd = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' })

/** 129999 => "$1,299.99". The API only ever sends integer cents. */
export function formatCents(cents: number): string {
  return usd.format(cents / 100)
}

/** 1000 => "10%", 250 => "2.5%", 25 => "0.25%". Same output as Discount::formatPercent in PHP. */
export function formatBasisPoints(basisPoints: number): string {
  return `${Number((basisPoints / 100).toFixed(2))}%`
}

const RULE_NAMES: Record<string, string> = {
  quantity: 'Quantity',
  category: 'Category',
  loyalty: 'Loyal customer',
}

/**
 * Order items store only the rule key and percentage (the quote's label isn't persisted),
 * so the order screen builds a short label like "Quantity: 10%".
 */
export function discountLabel(rule: DiscountRule, basisPoints: number): string {
  return `${RULE_NAMES[rule] ?? rule}: ${formatBasisPoints(basisPoints)}`
}

const dateTime = new Intl.DateTimeFormat('en-US', { dateStyle: 'medium', timeStyle: 'short' })

export function formatDateTime(iso: string): string {
  return dateTime.format(new Date(iso))
}
