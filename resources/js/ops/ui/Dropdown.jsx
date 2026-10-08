import { Check, ChevronDown } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'

import { cn } from '../lib/cn'

export function Dropdown({ label, icon: Icon, title, active = false, align = 'left', width = 'w-60', children }) {
    const [open, setOpen] = useState(false)
    const root = useRef(null)

    useEffect(() => {
        if (!open) {
            return undefined
        }

        function close(event) {
            if (event.type === 'keydown' ? event.key === 'Escape' : !root.current?.contains(event.target)) {
                setOpen(false)
            }
        }

        document.addEventListener('mousedown', close)
        document.addEventListener('keydown', close)

        return () => {
            document.removeEventListener('mousedown', close)
            document.removeEventListener('keydown', close)
        }
    }, [open])

    return (
        <div ref={root} className="relative">
            <button
                type="button"
                onClick={() => setOpen((previous) => !previous)}
                aria-expanded={open}
                aria-label={title}
                title={title}
                className={cn(
                    'relative inline-flex h-6 items-center gap-1.5 rounded border px-1.5 text-[0.6875rem] whitespace-nowrap',
                    active && !label ? 'border-zinc-800 bg-zinc-800 text-white' : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-100',
                    open && 'ring-2 ring-zinc-200',
                )}
            >
                {Icon && <Icon className="size-3.5" />}
                {label}
                {label && <ChevronDown className={cn('size-3 text-zinc-400 transition-transform', open && 'rotate-180')} />}
            </button>
            {open && (
                <div className={cn('absolute top-full z-40 mt-1 rounded border border-zinc-200 bg-white py-1 shadow-lg', width, align === 'right' ? 'right-0' : 'left-0')}>
                    {typeof children === 'function' ? children({ close: () => setOpen(false) }) : children}
                </div>
            )}
        </div>
    )
}

export function DropdownSection({ title, children }) {
    return (
        <div className="border-b border-zinc-100 py-1 last:border-0">
            {title && <p className="px-2.5 pt-0.5 pb-1 text-[0.625rem] font-semibold tracking-wide text-zinc-400 uppercase">{title}</p>}
            {children}
        </div>
    )
}

export function DropdownOption({ selected, onSelect, hint, children }) {
    return (
        <button type="button" onClick={onSelect} className="flex w-full items-center gap-2 px-2.5 py-1 text-left text-xs text-zinc-700 hover:bg-zinc-50">
            <Check className={cn('size-3 shrink-0', selected ? 'text-zinc-800' : 'invisible')} />
            <span className="flex-1">{children}</span>
            {hint !== undefined && <span className="text-[0.6875rem] text-zinc-400 tabular-nums">{hint}</span>}
        </button>
    )
}
