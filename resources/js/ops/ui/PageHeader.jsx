import { cn } from '../lib/cn'

export function PageHeader({ title, description, actions, className, children }) {
    return (
        <div className={cn('flex flex-wrap items-center justify-between gap-3', className)}>
            <div>
                <h1 className="text-lg font-semibold text-zinc-900">{title}</h1>
                {description && <p className="mt-0.5 text-[0.8125rem] text-zinc-500">{description}</p>}
            </div>
            {(actions || children) && <div className="flex items-center gap-2">{actions ?? children}</div>}
        </div>
    )
}
