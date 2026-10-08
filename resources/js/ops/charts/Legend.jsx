import { cn } from '../lib/cn'

const SWATCH = {
    schedule: 'bg-ops-schedule',
    web: 'bg-ops-web',
    other: 'bg-ops-other',
    ink: 'bg-ops-ink',
    fail: 'bg-ops-fail',
    warn: 'bg-ops-warn',
    ok: 'bg-ops-ok',
    muted: 'bg-ops-muted',
}

const BORDER = { warn: 'border-ops-warn', fail: 'border-ops-fail', ink: 'border-ops-ink' }

function Swatch({ tone, shape = 'box' }) {
    if (shape === 'dash') {
        return <span className={cn('inline-block w-3.5 border-t-2 border-dashed', BORDER[tone] ?? 'border-zinc-400')} />
    }

    if (shape === 'line') {
        return <span className={cn('inline-block w-3.5 border-t-2', BORDER[tone] ?? 'border-zinc-400')} />
    }

    if (shape === 'dot') {
        return <span className={cn('inline-block size-2 rounded-full', SWATCH[tone] ?? 'bg-zinc-400')} />
    }

    return <span className={cn('inline-block size-2.5 rounded-sm', SWATCH[tone] ?? 'bg-zinc-400')} />
}

export function Legend({ items }) {
    return (
        <span className="inline-flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.6875rem] font-normal text-zinc-500">
            {items.map((item) => (
                <span key={item.label} className="inline-flex items-center gap-1.5">
                    <Swatch tone={item.tone} shape={item.shape} />
                    {item.label}
                </span>
            ))}
        </span>
    )
}
