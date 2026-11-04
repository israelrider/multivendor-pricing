import { useQuery } from '@tanstack/react-query'

// Placeholder shell for step 1: proves Vite, Tailwind, TanStack Query and the
// /api proxy to Laravel are wired up. Real pages arrive in step 5.
export default function App() {
  const ping = useQuery({
    queryKey: ['ping'],
    queryFn: async () => {
      const res = await fetch('/api/ping', { headers: { Accept: 'application/json' } })
      if (!res.ok) throw new Error(`HTTP ${res.status}`)
      return (await res.json()) as { status: string }
    },
  })

  return (
    <main className="min-h-screen bg-slate-50 p-6 text-slate-900">
      <h1 className="text-2xl font-semibold">Multi-Vendor Shop</h1>
      <p className="mt-2 text-sm text-slate-600">
        API:{' '}
        {ping.isPending ? 'checking…' : ping.isError ? (
          <span className="text-red-600">unreachable ({ping.error.message})</span>
        ) : (
          <span className="text-emerald-700">{ping.data.status}</span>
        )}
      </p>
    </main>
  )
}
