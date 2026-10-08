import { Link, usePage } from '@inertiajs/react'
import { Menu } from 'lucide-react'
import { useState } from 'react'

import { t } from '../i18n'
import { cn } from '../lib/cn'
import { Icon } from '../ui'
import { FlashToast } from './FlashToast'

function groupNav(items) {
    const groups = []

    items.forEach((item) => {
        const key = item.group ?? ''
        let group = groups.find((candidate) => candidate.key === key)

        if (!group) {
            group = { key, items: [] }
            groups.push(group)
        }

        group.items.push(item)
    })

    return groups
}

function NavLink({ item, onNavigate }) {
    const className = cn(
        'flex items-center gap-2 rounded px-2 py-1.5 text-xs',
        item.active ? 'bg-ops-accent-50 font-medium text-ops-accent' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900',
    )

    if (item.external) {
        return (
            <a href={item.url} target="_blank" rel="noreferrer" className={className}>
                <Icon name={item.icon} className="size-3.5" />
                {t(item.label)}
            </a>
        )
    }

    return (
        <Link href={item.url} className={className} onClick={onNavigate}>
            <Icon name={item.icon} className="size-3.5" />
            {t(item.label)}
        </Link>
    )
}

function Sidebar({ nav, open, onClose }) {
    return (
        <aside className={cn('fixed inset-y-0 left-0 z-30 w-48 border-r border-zinc-200 bg-white p-2 transition-transform md:translate-x-0', open ? 'translate-x-0' : '-translate-x-full')}>
            {groupNav(nav).map((group) => (
                <div key={group.key || 'root'} className="mb-3">
                    {group.key && <div className="px-2 pb-1 text-[0.625rem] font-semibold tracking-wide text-zinc-400 uppercase">{t(group.key)}</div>}
                    {group.items.map((item) => (
                        <NavLink key={item.url} item={item} onNavigate={onClose} />
                    ))}
                </div>
            ))}
        </aside>
    )
}

export function OpsShell({ toolbar, children }) {
    const { ops } = usePage().props
    const [open, setOpen] = useState(false)

    return (
        <div className="min-h-screen bg-zinc-50 text-xs text-zinc-800">
            <Sidebar nav={ops?.nav ?? []} open={open} onClose={() => setOpen(false)} />
            <div className="md:ml-48">
                <header className="sticky top-0 z-20 flex h-10 items-center gap-2 border-b border-zinc-200 bg-white px-3">
                    <button type="button" className="rounded p-1 text-zinc-500 hover:bg-zinc-100 md:hidden" onClick={() => setOpen((value) => !value)} aria-label="Menu">
                        <Menu className="size-4" />
                    </button>
                    <Link href={ops?.home_url ?? '/'} className="text-sm font-semibold text-zinc-900">
                        Ops
                    </Link>
                    <span className="text-zinc-400">
                        {ops?.app_name} · {ops?.environment}
                    </span>
                    <div className="ml-auto">{toolbar}</div>
                </header>
                <main className="p-3">{children}</main>
            </div>
            <FlashToast />
        </div>
    )
}
