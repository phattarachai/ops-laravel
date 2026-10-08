import { cn } from '../lib/cn'

const CONTROL =
    'w-full rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-[0.8125rem] text-zinc-900 transition placeholder:text-zinc-400 focus:border-ops-accent focus:outline-none disabled:opacity-50'

export function FormField({ label, error, hint, htmlFor, className, children }) {
    return (
        <div className={cn('space-y-1', className)}>
            {label && (
                <label htmlFor={htmlFor} className="block text-xs font-medium text-zinc-600">
                    {label}
                </label>
            )}
            {children}
            {hint && !error && <p className="text-xs text-zinc-400">{hint}</p>}
            {error && <p className="text-xs text-red-600">{error}</p>}
        </div>
    )
}

export function Input({ className, ...props }) {
    return <input className={cn(CONTROL, className)} {...props} />
}

export function Textarea({ className, ...props }) {
    return <textarea className={cn(CONTROL, 'min-h-20', className)} {...props} />
}

export function Select({ className, children, ...props }) {
    return (
        <select className={cn(CONTROL, className)} {...props}>
            {children}
        </select>
    )
}
