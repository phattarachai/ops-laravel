import { cn } from '../lib/cn'

export function Section({ title, hint, tooltip, actions, flush = false, className, children }) {
    return (
        <section className={cn('min-w-0 rounded border border-zinc-200 bg-white', className)}>
            {(title || actions) && (
                <header className="flex min-h-8 flex-wrap items-center gap-2 border-b border-zinc-100 px-2.5 py-1">
                    {title && (
                        <h2 className={cn('text-xs font-semibold text-zinc-800', tooltip && 'cursor-help')} title={tooltip}>
                            {title}
                        </h2>
                    )}
                    {hint && <span className="text-[0.6875rem] text-zinc-500">{hint}</span>}
                    {actions && <div className="ml-auto flex items-center gap-2">{actions}</div>}
                </header>
            )}
            <div className={cn(!flush && 'p-2.5')}>{children}</div>
        </section>
    )
}
