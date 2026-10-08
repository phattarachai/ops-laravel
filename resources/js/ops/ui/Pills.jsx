import { cn } from '../lib/cn'

export function Pills({ options, value, onChange, className }) {
    return (
        <div className={cn('inline-flex overflow-hidden rounded border border-zinc-300 bg-white', className)} role="group">
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    onClick={() => onChange(option.value)}
                    aria-pressed={option.value === value}
                    className={cn(
                        'border-r border-zinc-200 px-2 py-0.5 text-[0.6875rem] whitespace-nowrap last:border-r-0',
                        option.value === value ? 'bg-zinc-800 text-white' : 'text-zinc-600 hover:bg-zinc-100',
                    )}
                >
                    {option.label}
                    {option.count !== undefined && <span className={cn('ml-1 tabular-nums', option.value === value ? 'text-zinc-300' : 'text-zinc-400')}>{option.count}</span>}
                </button>
            ))}
        </div>
    )
}
