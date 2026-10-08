import { useState } from 'react'

import { cn } from '../lib/cn'

function readStored(storageKey) {
    try {
        return storageKey ? localStorage.getItem(storageKey) : null
    } catch {
        return null
    }
}

function store(storageKey, value) {
    try {
        if (storageKey) {
            localStorage.setItem(storageKey, value)
        }
    } catch {
        return undefined
    }
}

function Tab({ tab, active, onSelect }) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            onClick={onSelect}
            className={cn(
                '-mb-px inline-flex items-center gap-1.5 border-b-2 px-1 py-1.5 text-xs font-semibold whitespace-nowrap',
                active ? 'border-zinc-800 text-zinc-900' : 'border-transparent text-zinc-500 hover:text-zinc-800',
            )}
        >
            {tab.label}
            {tab.count !== undefined && (
                <span className={cn('rounded-full px-1.5 text-[0.625rem] leading-4 tabular-nums', active ? 'bg-zinc-800 text-white' : 'bg-zinc-100 text-zinc-500')}>{tab.count}</span>
            )}
        </button>
    )
}

export function TabSection({ tabs, defaultTab, storageKey, flush = false, className }) {
    const [selected, setSelected] = useState(() => readStored(storageKey) ?? defaultTab ?? tabs[0]?.key)
    const active = tabs.find((tab) => tab.key === selected) ?? tabs[0]

    function select(key) {
        setSelected(key)
        store(storageKey, key)
    }

    return (
        <section className={cn('min-w-0 rounded border border-zinc-200 bg-white', className)}>
            <header className="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-zinc-100 px-2.5">
                <div role="tablist" className="flex items-center gap-3">
                    {tabs.map((tab) => (
                        <Tab key={tab.key} tab={tab} active={tab.key === active.key} onSelect={() => select(tab.key)} />
                    ))}
                </div>
                {active.hint && <span className="text-[0.6875rem] text-zinc-500">{active.hint}</span>}
                {active.actions && <div className="ml-auto flex items-center gap-2 py-1">{active.actions}</div>}
            </header>
            <div role="tabpanel" className={cn(!flush && 'p-2.5')}>
                {active.content}
            </div>
        </section>
    )
}
