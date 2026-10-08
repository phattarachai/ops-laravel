import { cn } from '../lib/cn'

const TONES = {
    zinc: 'border-zinc-200 bg-zinc-50 text-zinc-600',
    accent: 'border-ops-accent-50 bg-ops-accent-50 text-ops-accent-600',
    green: 'border-green-200 bg-green-50 text-green-700',
    amber: 'border-amber-200 bg-amber-50 text-amber-700',
    red: 'border-red-200 bg-red-50 text-red-700',
    blue: 'border-blue-200 bg-blue-50 text-blue-700',
}

const ALIASES = { success: 'green', warning: 'amber', danger: 'red', gray: 'zinc', info: 'blue' }

export function Badge({ tone = 'zinc', className, children }) {
    return (
        <span className={cn('inline-flex items-center gap-1 rounded-sm border px-1.5 text-[0.6875rem] leading-[18px] font-medium whitespace-nowrap', TONES[ALIASES[tone] ?? tone] ?? TONES.zinc, className)}>
            {children}
        </span>
    )
}
