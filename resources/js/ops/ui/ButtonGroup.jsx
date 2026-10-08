import { Icon } from './Icon'
import { cn } from '../lib/cn'

export function ButtonGroup({ options, value, onChange, className }) {
    return (
        <div className={cn('inline-flex rounded-md border border-zinc-300 bg-zinc-100 p-0.5', className)} role="group">
            {options.map((option) => {
                const active = option.value === value

                return (
                    <button
                        key={option.value}
                        type="button"
                        onClick={() => onChange(option.value)}
                        aria-pressed={active}
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded px-3 py-1 text-[0.8125rem] font-medium transition',
                            active
                                ? 'bg-white text-zinc-900 shadow-sm'
                                : 'text-zinc-500 hover:text-zinc-800',
                        )}
                    >
                        {option.icon && <Icon name={option.icon} className="size-3.5" />}
                        {option.label}
                    </button>
                )
            })}
        </div>
    )
}
