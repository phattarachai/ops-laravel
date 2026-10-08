import { cn } from '../lib/cn'

export function SectionGrid({ min = '32rem', className, children }) {
    return (
        <div className={cn('grid items-start gap-3', className)} style={{ gridTemplateColumns: `repeat(auto-fit, minmax(min(100%, ${min}), 1fr))` }}>
            {children}
        </div>
    )
}

export function SectionStack({ className, children }) {
    return <div className={cn('min-w-0 space-y-3', className)}>{children}</div>
}
