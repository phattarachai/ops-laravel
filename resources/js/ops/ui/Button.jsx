import { Icon } from './Icon'
import { cn } from '../lib/cn'

const VARIANTS = {
    accent: 'bg-ops-accent text-white hover:bg-ops-accent-600',
    outline: 'border border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50',
    ghost: 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900',
    danger: 'bg-red-600 text-white hover:bg-red-700',
}

const SIZES = {
    sm: 'gap-1 px-2.5 py-1 text-xs',
    md: 'gap-1.5 px-3 py-1.5 text-[0.8125rem]',
}

export function Button({ variant = 'accent', size = 'md', icon, iconEnd, className, type = 'button', children, ...props }) {
    return (
        <button
            type={type}
            className={cn(
                'inline-flex items-center justify-center rounded-md font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ops-accent disabled:pointer-events-none disabled:opacity-50',
                SIZES[size],
                VARIANTS[variant],
                className,
            )}
            {...props}
        >
            {icon && <Icon name={icon} className="size-4" />}
            {children}
            {iconEnd && <Icon name={iconEnd} className="size-4" />}
        </button>
    )
}
