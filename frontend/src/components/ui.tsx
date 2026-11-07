import type { ButtonHTMLAttributes, HTMLAttributes, ReactNode } from 'react'
import { formatCents } from '../lib/format'

// Small in-house building blocks (README, "Frontend flow"). Neutral slate palette, one indigo accent.

function cx(...classes: (string | false | null | undefined)[]): string {
  return classes.filter(Boolean).join(' ')
}

export function Spinner({ className }: { className?: string }) {
  return (
    <span
      role="status"
      aria-label="Loading"
      className={cx('inline-block size-4 animate-spin rounded-full border-2 border-current border-r-transparent', className)}
    />
  )
}

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'secondary' | 'ghost'
  size?: 'sm' | 'md'
  loading?: boolean
}

export function Button({ variant = 'primary', size = 'md', loading = false, disabled, className, children, ...props }: ButtonProps) {
  return (
    <button
      type="button"
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={cx(
        'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600',
        'disabled:cursor-not-allowed disabled:opacity-50',
        size === 'sm' ? 'h-8 px-3 text-sm' : 'h-10 px-4 text-sm',
        variant === 'primary' && 'bg-indigo-600 text-white hover:bg-indigo-700',
        variant === 'secondary' && 'border border-slate-300 bg-white text-slate-800 hover:bg-slate-50',
        variant === 'ghost' && 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
        className,
      )}
      {...props}
    >
      {loading && <Spinner />}
      {children}
    </button>
  )
}

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cx('rounded-xl border border-slate-200 bg-white shadow-sm', className)} {...props} />
}

type BadgeTone = 'neutral' | 'accent' | 'success' | 'warning' | 'danger'

const BADGE_TONES: Record<BadgeTone, string> = {
  neutral: 'bg-slate-100 text-slate-700 ring-slate-200',
  accent: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
  success: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  warning: 'bg-amber-50 text-amber-800 ring-amber-200',
  danger: 'bg-red-50 text-red-700 ring-red-200',
}

export function Badge({ tone = 'neutral', className, children }: { tone?: BadgeTone; className?: string; children: ReactNode }) {
  return (
    <span
      className={cx(
        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset',
        BADGE_TONES[tone],
        className,
      )}
    >
      {children}
    </span>
  )
}

export function Money({ cents, className }: { cents: number; className?: string }) {
  return <span className={cx('tabular-nums', className)}>{formatCents(cents)}</span>
}

export function Skeleton({ className }: { className?: string }) {
  return <div aria-hidden className={cx('animate-pulse rounded-md bg-slate-200', className)} />
}

export function ErrorBanner({
  tone = 'danger',
  title,
  children,
  action,
}: {
  tone?: 'danger' | 'warning'
  title: string
  children?: ReactNode
  action?: ReactNode
}) {
  return (
    <div
      role="alert"
      className={cx(
        'flex flex-col gap-3 rounded-xl border p-4 text-sm sm:flex-row sm:items-start sm:justify-between',
        tone === 'danger' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-900',
      )}
    >
      <div>
        <p className="font-semibold">{title}</p>
        {children && <div className="mt-1">{children}</div>}
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  )
}

/** Subtotal / discount / total rows, shared by the cart, review and order screens. */
export function TotalsList({
  subtotalCents,
  discountCents,
  totalCents,
  size = 'md',
}: {
  subtotalCents: number
  discountCents: number
  totalCents: number
  size?: 'sm' | 'md'
}) {
  return (
    <dl className={cx('space-y-1', size === 'sm' ? 'text-sm' : 'text-sm sm:text-base')}>
      <div className="flex justify-between text-slate-600">
        <dt>Subtotal</dt>
        <dd>
          <Money cents={subtotalCents} />
        </dd>
      </div>
      <div className="flex justify-between text-emerald-700">
        <dt>Discounts</dt>
        <dd>
          {discountCents > 0 ? '−' : ''}
          <Money cents={discountCents} />
        </dd>
      </div>
      <div className={cx('flex justify-between font-semibold text-slate-900', size === 'md' && 'border-t border-slate-200 pt-2')}>
        <dt>Total</dt>
        <dd>
          <Money cents={totalCents} />
        </dd>
      </div>
    </dl>
  )
}

export function PageHeader({ title, description, actions }: { title: string; description?: ReactNode; actions?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">{title}</h1>
        {description && <p className="mt-1 text-sm text-slate-600">{description}</p>}
      </div>
      {actions}
    </div>
  )
}
