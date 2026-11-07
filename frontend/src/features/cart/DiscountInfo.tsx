import type { QuotedLine } from '../../api/types'
import { Badge } from '../../components/ui'

/**
 * The applied discount as a badge, plus the other discounts the line also qualifies for.
 * Only the best one applies, and showing the rest explains why.
 */
export function DiscountInfo({ line }: { line: QuotedLine }) {
  const alsoEligible = line.applicable_discounts.filter((discount) => !discount.applied)

  if (!line.applied_discount) return null

  return (
    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
      <Badge tone="accent">{line.applied_discount.label}</Badge>
      {alsoEligible.length > 0 && (
        <span className="text-xs text-slate-500">
          Also eligible: {alsoEligible.map((discount) => discount.label).join(', ')} (best discount wins)
        </span>
      )}
    </div>
  )
}
