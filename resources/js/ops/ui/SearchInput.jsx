import { Search } from 'lucide-react'
import { useEffect, useRef } from 'react'

import { cn } from '../lib/cn'
import { t } from '../i18n'

export function SearchInput({ value, onChange, onSearch, debounce = 300, placeholder = t('Search…'), className, inputClassName }) {
    const timer = useRef(null)

    useEffect(() => () => clearTimeout(timer.current), [])

    function change(next) {
        onChange(next)

        if (!onSearch) {
            return
        }

        clearTimeout(timer.current)
        timer.current = setTimeout(() => onSearch(next.trim()), debounce)
    }

    return (
        <label className={cn('relative inline-flex items-center', className)}>
            <Search className="pointer-events-none absolute left-1.5 size-3 text-zinc-400" />
            <input
                type="search"
                value={value}
                onChange={(event) => change(event.target.value)}
                placeholder={placeholder}
                className={cn(
                    'h-6 w-48 rounded border border-zinc-300 bg-white pr-2 pl-6 text-[0.6875rem] text-zinc-700 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none',
                    inputClassName,
                )}
            />
        </label>
    )
}
