import { router, usePage, usePoll } from '@inertiajs/react'
import { CalendarRange, RefreshCw } from 'lucide-react'
import { useState } from 'react'

import { Dropdown, DropdownOption, DropdownSection } from '../ui/Dropdown'
import { cn } from '../lib/cn'
import { t } from '../i18n'

const POLL_SECONDS = 30

function visit(params) {
    const query = Object.fromEntries(new URLSearchParams(window.location.search))
    delete query.window
    delete query.from
    delete query.to

    router.get(window.location.pathname, { ...query, ...params }, { preserveScroll: true, preserveState: true })
}

function CustomRange({ filter, onApply }) {
    const [from, setFrom] = useState(filter.from ?? '')
    const [to, setTo] = useState(filter.to ?? '')
    const input = 'h-6 rounded border border-zinc-300 bg-white px-1.5 text-[0.6875rem] text-zinc-700'

    return (
        <form
            className="flex items-center gap-1"
            onSubmit={(event) => {
                event.preventDefault()
                onApply()
                visit({ window: 'custom', from, to })
            }}
        >
            <input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className={input} aria-label={t('From')} required />
            <span className="text-zinc-400">–</span>
            <input type="date" value={to} onChange={(event) => setTo(event.target.value)} className={input} aria-label={t('To')} required />
            <button type="submit" className="h-6 rounded bg-ops-accent px-2 text-[0.6875rem] font-medium text-white hover:bg-ops-accent-600">
                {t('Apply')}
            </button>
        </form>
    )
}

function Freshness({ filter, polling, onToggle }) {
    return (
        <button
            type="button"
            onClick={onToggle}
            title={filter.as_of_human ? `${filter.as_of_human} · ${t('Click to :action auto-refresh', { action: t(polling ? 'pause' : 'resume') })}` : undefined}
            className="hidden items-center gap-1.5 text-[0.6875rem] whitespace-nowrap text-zinc-500 hover:text-zinc-800 lg:inline-flex"
        >
            <span className={cn('size-1.5 rounded-full', polling ? 'bg-ops-ok' : 'bg-zinc-300')} />
            {filter.as_of ? t('Data until :time', { time: filter.as_of }) : t('No data yet')}
            {polling && <span className="text-zinc-400">· {t('every :seconds s', { seconds: POLL_SECONDS })}</span>}
        </button>
    )
}

function RangeMenu({ filter }) {
    const presets = filter.presets.filter((preset) => preset.value !== 'custom')
    const custom = filter.presets.find((preset) => preset.value === 'custom')

    return (
        <Dropdown icon={CalendarRange} label={filter.label} title={t('Time range')} align="right" width="w-80">
            {({ close }) => (
                <>
                    <DropdownSection title={t('Time range')}>
                        {presets.map((preset) => (
                            <DropdownOption
                                key={preset.value}
                                selected={filter.window === preset.value}
                                onSelect={() => {
                                    close()
                                    visit(preset.value === '24h' ? {} : { window: preset.value })
                                }}
                            >
                                {t(preset.label)}
                            </DropdownOption>
                        ))}
                    </DropdownSection>
                    {custom && (
                        <DropdownSection title={t(custom.label)}>
                            <div className="px-2.5 pb-1">
                                <CustomRange filter={filter} onApply={close} />
                            </div>
                        </DropdownSection>
                    )}
                </>
            )}
        </Dropdown>
    )
}

export function TimeFilter({ prop = 'time_filter', presets = true, pollOptions = {} }) {
    const filter = usePage().props[prop]
    const [refreshing, setRefreshing] = useState(false)
    const { polling, start, stop } = usePoll(POLL_SECONDS * 1000, pollOptions)

    function refresh() {
        router.post(
            filter.refresh_url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setRefreshing(true),
                onFinish: () => setRefreshing(false),
            },
        )
    }

    return (
        <div className="flex items-center gap-2">
            {presets && <RangeMenu filter={filter} />}
            <Freshness filter={filter} polling={polling} onToggle={polling ? stop : start} />
            <button type="button" onClick={refresh} disabled={refreshing} title={t('Refresh (skip cache)')} className="rounded border border-zinc-300 bg-white p-1 text-zinc-600 hover:bg-zinc-100 disabled:opacity-50">
                <RefreshCw className={cn('size-3', refreshing && 'animate-spin')} />
            </button>
        </div>
    )
}
