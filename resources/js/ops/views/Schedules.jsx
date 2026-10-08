import { SlidersHorizontal } from 'lucide-react'
import { useState } from 'react'

import { Kpi, KpiStrip } from '../charts/Kpi'
import { TimeFilter } from '../charts/TimeFilter'
import { t } from '../i18n'
import { cn } from '../lib/cn'
import { number } from '../lib/format'
import { relative } from '../lib/time'
import { usePersistentState } from '../lib/usePersistentState'
import { OpsPage } from '../shell/OpsPage'
import { Badge } from '../ui/Badge'
import { DataTable } from '../ui/DataTable'
import { Dropdown, DropdownOption, DropdownSection } from '../ui/Dropdown'
import { Pills } from '../ui/Pills'
import { SearchInput } from '../ui/SearchInput'
import { timelineUrl, visit } from './jobs/shared'
import { cadenceLabel, GROUP_ORDER, groupTasks } from './schedules/cadence'
import { DayPicker } from './schedules/DayPicker'
import { DayTimeline, TimelineLegend } from './schedules/DayTimeline'
import { HistoryLegend, HistoryStrip } from './schedules/HistoryStrip'
import { ScheduleMatrix } from './schedules/ScheduleMatrix'

const LAST_TONES = { success: 'bg-ops-ok', failed: 'bg-ops-fail' }

function modes() {
    return [
        { value: 'list', label: t('List') },
        { value: 'timeline', label: t('Day timeline') },
        { value: 'matrix', label: t('14-day history') },
    ]
}

function statusFilters() {
    return [
        { value: 'all', label: t('All'), match: () => true },
        { value: 'problem', label: t('Has problems'), match: (task) => task.health === 'failing' || task.health === 'missed' },
        { value: 'ok', label: t('Healthy'), match: (task) => task.health === 'ok' },
        { value: 'no_data', label: t('No data'), match: (task) => task.health === 'no_data' },
    ]
}

function listColumns({ dates, showClass }) {
    return [
        {
            key: 'name',
            label: 'Task',
            className: 'w-full max-w-0 min-w-56 overflow-hidden',
            sortable: true,
            render: (task) => (
                <span className="block min-w-56" title={task.task}>
                    <span className="block truncate font-semibold text-zinc-900 group-hover:text-ops-accent-600">{task.name}</span>
                    {showClass && task.name !== task.task && <span className="block truncate text-[0.6875rem] text-zinc-400">{task.namespace ?? task.task}</span>}
                </span>
            ),
        },
        { key: 'cadence', label: t('Cadence'), render: (task) => <span title={task.expressions.join(' · ')}>{cadenceLabel(task.cadence)}</span> },
        { key: 'health', label: t('Status'), render: (task) => <Badge tone={task.health_color}>{t(task.health_label)}</Badge> },
        { key: 'history', label: t(':count days', { count: dates.length }), render: (task) => <HistoryStrip history={task.history} dates={dates} /> },
        { key: 'runs', label: 'Runs', align: 'right', sortable: true, sortDir: 'desc', render: (task) => number(task.runs) },
        {
            key: 'failed',
            label: 'Failed',
            align: 'right',
            sortable: true,
            sortDir: 'desc',
            render: (task) => <span className={cn(task.failed > 0 ? 'font-semibold text-ops-fail' : 'text-zinc-400')}>{number(task.failed)}</span>,
        },
        {
            key: 'missed',
            label: <span title={t('Cron ticks in the last 24 h with no run or skip within 5 minutes (counted from the first recorded day)')}>Missed · 24h</span>,
            align: 'right',
            sortable: true,
            sortDir: 'desc',
            render: (task) => <span className={cn(task.missed > 0 ? 'font-semibold text-ops-warn' : 'text-zinc-400')}>{task.missed ?? '—'}</span>,
        },
        { key: 'p95', label: 'p95', align: 'right', sortable: true, sortDir: 'desc', sortValue: (task) => task.p95_seconds },
        {
            key: 'last_run_at',
            label: t('Last run'),
            sortable: true,
            sortDir: 'desc',
            sortValue: (task) => task.last_run_at_iso,
            render: (task) => (
                <span className="inline-flex items-center gap-1.5 text-zinc-500" title={task.last_status ?? undefined}>
                    <span className={cn('size-1.5 rounded-full', LAST_TONES[task.last_status] ?? 'bg-zinc-300')} />
                    {task.last_run_at}
                    {task.last_run_at_iso && <span className="text-zinc-400">· {task.last_duration}</span>}
                </span>
            ),
        },
        {
            key: 'next_due',
            label: t('Next'),
            align: 'right',
            sortable: true,
            sortValue: (task) => task.next_due_at,
            render: (task) => (
                <span className="text-zinc-500" title={task.next_due ?? undefined}>
                    {relative(task.next_due_at) ?? '—'}
                </span>
            ),
        },
    ]
}

function Summary({ tasks }) {
    const failing = tasks.filter((task) => task.health === 'failing').length
    const missed = tasks.reduce((total, task) => total + (task.missed ?? 0), 0)
    const failed = tasks.reduce((total, task) => total + task.failed, 0)

    return (
        <KpiStrip>
            <Kpi label="Tasks" value={number(tasks.length)} />
            <Kpi label="Failing" value={number(failing)} tone={failing > 0 ? 'fail' : undefined} />
            <Kpi label="Missed · 24h" value={number(missed)} tone={missed > 0 ? 'warn' : undefined} />
            <Kpi label="Runs" value={number(tasks.reduce((total, task) => total + task.runs, 0))} />
            <Kpi label={t('Failed runs')} value={number(failed)} tone={failed > 0 ? 'fail' : undefined} />
        </KpiStrip>
    )
}

function ListView({ tasks, dates, grouped, showClass }) {
    const groups = groupTasks(tasks, GROUP_ORDER.list)
    const shared = { columns: listColumns({ dates, showClass }), rowKey: 'task', rowHref: (task) => task.url, empty: t('No task matches the filter') }

    if (!grouped) {
        return <DataTable {...shared} rows={groups.flatMap((group) => group.rows)} />
    }

    return <DataTable {...shared} groups={groups.map((group) => ({ ...group, hint: t(':count tasks', { count: group.rows.length }) }))} />
}

function TimelineView({ timeline, visible }) {
    const names = new Set(visible.map((task) => task.task))
    const groups = groupTasks(
        timeline.tasks.filter((task) => names.has(task.task)),
        GROUP_ORDER.timeline,
    )

    return (
        <DayTimeline
            groups={groups}
            isToday={timeline.date === timeline.today}
            toolbar={<DayPicker date={timeline.date} today={timeline.today} label={timeline.label} onChange={(day) => visit({ day: day === timeline.today ? undefined : day })} />}
        />
    )
}

function DisplayMenu({ mode, tasks, settings, onChange, silencedCount }) {
    const { status, grouped, showClass, showSilenced } = settings
    const filters = statusFilters()
    const current = filters.find((filter) => filter.value === status)
    const customised = status !== 'all' || showSilenced || (mode === 'list' && (!grouped || showClass))

    return (
        <Dropdown icon={SlidersHorizontal} title={status === 'all' ? t('Filter & display') : `${t('Filter')} · ${current.label}`} active={customised}>
            <DropdownSection title={t('Status')}>
                {filters.map((filter) => (
                    <DropdownOption key={filter.value} selected={status === filter.value} onSelect={() => onChange({ status: filter.value })} hint={tasks.filter(filter.match).length}>
                        {filter.label}
                    </DropdownOption>
                ))}
            </DropdownSection>
            <DropdownSection title={t('Show')}>
                {mode === 'list' && (
                    <>
                        <DropdownOption selected={grouped} onSelect={() => onChange({ grouped: !grouped })}>
                            {t('Group by cadence')}
                        </DropdownOption>
                        <DropdownOption selected={showClass} onSelect={() => onChange({ showClass: !showClass })}>
                            {t('Job class namespace')}
                        </DropdownOption>
                    </>
                )}
                <DropdownOption selected={showSilenced} onSelect={() => onChange({ showSilenced: !showSilenced })} hint={silencedCount}>
                    {t('Silenced jobs')}
                </DropdownOption>
            </DropdownSection>
        </Dropdown>
    )
}

function useScheduleSettings() {
    const [status, setStatus] = useState('all')
    const [grouped, setGrouped] = usePersistentState('ops-schedules-grouped', true)
    const [showClass, setShowClass] = usePersistentState('ops-schedules-show-class', false)
    const [showSilenced, setShowSilenced] = usePersistentState('ops-schedules-show-silenced', false)
    const setters = { status: setStatus, grouped: setGrouped, showClass: setShowClass, showSilenced: setShowSilenced }

    function change(next) {
        Object.entries(next).forEach(([key, value]) => setters[key](value))
    }

    return [{ status, grouped, showClass, showSilenced }, change]
}

export default function Schedules({ mode, tasks, dates, timeline }) {
    const [search, setSearch] = useState('')
    const [settings, change] = useScheduleSettings()

    const shown = settings.showSilenced ? tasks : tasks.filter((task) => !task.silenced)
    const term = search.trim().toLowerCase()
    const matcher = statusFilters().find((filter) => filter.value === settings.status).match
    const visible = shown.filter((task) => matcher(task) && (term === '' || task.task.toLowerCase().includes(term) || cadenceLabel(task.cadence).toLowerCase().includes(term)))

    return (
        <OpsPage title="Schedules" toolbar={<TimeFilter presets={mode === 'list'} />}>
            <div className="space-y-3">
                <Summary tasks={shown} />

                <div className="flex flex-wrap items-center gap-2">
                    <Pills options={modes()} value={mode} onChange={(next) => visit({ view: next === 'list' ? undefined : next, day: undefined })} />
                    <SearchInput value={search} onChange={setSearch} placeholder={t('Search task / cadence…')} />
                    <DisplayMenu mode={mode} tasks={shown} settings={settings} onChange={change} silencedCount={tasks.filter((task) => task.silenced).length} />
                    <span className="ml-auto">{mode === 'timeline' ? <TimelineLegend /> : <HistoryLegend />}</span>
                </div>

                {mode === 'list' && <ListView tasks={visible} dates={dates} grouped={settings.grouped} showClass={settings.showClass} />}
                {mode === 'timeline' && timeline && <TimelineView timeline={timeline} visible={visible} />}
                {mode === 'matrix' && <ScheduleMatrix groups={groupTasks(visible, GROUP_ORDER.timeline)} dates={dates} timelineUrl={timelineUrl} />}
            </div>
        </OpsPage>
    )
}
