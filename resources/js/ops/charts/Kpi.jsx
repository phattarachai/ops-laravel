import { cn } from '../lib/cn'

const TONES = {
    fail: 'text-ops-fail',
    warn: 'text-ops-warn',
    ok: 'text-ops-ok',
}

export function Kpi({ label, value, hint, tone, children }) {
    return (
        <div className="min-w-0 rounded border border-zinc-200 bg-white px-2.5 py-1.5">
            <p className="truncate text-[0.625rem] font-semibold uppercase tracking-wide text-zinc-500">{label}</p>
            <p className={cn('text-lg leading-tight font-bold tabular-nums text-zinc-900', TONES[tone])}>{value}</p>
            {hint && <p className="truncate text-[0.6875rem] text-zinc-500">{hint}</p>}
            {children}
        </div>
    )
}

export function KpiStrip({ className, children }) {
    return <div className={cn('grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5 2xl:grid-cols-7', className)}>{children}</div>
}
