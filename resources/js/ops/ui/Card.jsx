import { cn } from '../lib/cn'

export function Card({ className, children, ...props }) {
    return (
        <div
            className={cn('rounded-lg border border-zinc-200 bg-white', className)}
            {...props}
        >
            {children}
        </div>
    )
}

export function CardHeader({ title, description, actions, className }) {
    return (
        <div className={cn('flex items-start justify-between gap-4 border-b border-zinc-100 px-4 py-3', className)}>
            <div>
                <h3 className="text-sm font-semibold text-zinc-900">{title}</h3>
                {description && <p className="mt-0.5 text-xs text-zinc-500">{description}</p>}
            </div>
            {actions && <div className="flex items-center gap-2">{actions}</div>}
        </div>
    )
}
