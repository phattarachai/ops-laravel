import { useState } from 'react'

import { Kpi, KpiStrip } from '../charts/Kpi'
import { TimeFilter } from '../charts/TimeFilter'
import { t } from '../i18n'
import { number } from '../lib/format'
import { usePersistentState } from '../lib/usePersistentState'
import { OpsPage } from '../shell/OpsPage'
import { Badge } from '../ui/Badge'
import { Pills } from '../ui/Pills'
import { SearchInput } from '../ui/SearchInput'
import { HeatmapLegend, HourHeatmap } from './jobs/HourHeatmap'
import { JobMatrix } from './jobs/JobMatrix'
import { DEFAULTS, JobsMenu } from './jobs/JobsMenu'
import { JobsList } from './jobs/JobsList'
import { JobStripLegend } from './jobs/JobStrip'
import { groupJobs, matchesQueue, matchesSearch, statusFilters, timelineUrl, visit } from './jobs/shared'
import { WorkersTable } from './jobs/WorkersTable'
import { DayPicker } from './schedules/DayPicker'

const WINDOWED = ['list', 'workers']

const byFirstRun = (a, b) => a.hours.findIndex((cell) => cell.runs > 0) - b.hours.findIndex((cell) => cell.runs > 0) || b.runs - a.runs

const byFailedThenRuns = (a, b) => b.failed - a.failed || b.runs - a.runs

function modes() {
    return [
        { value: 'list', label: t('List') },
        { value: 'matrix', label: t('14-day history') },
        { value: 'timeline', label: t('By hour') },
        { value: 'workers', label: t('Workers & queues') },
    ]
}

function rowsFor(mode, { jobs, matrix, timeline }) {
    return { matrix: matrix ?? [], timeline: timeline?.jobs ?? [] }[mode] ?? jobs
}

function Summary({ rows }) {
    const failing = rows.filter((row) => row.failed > 0).length
    const runs = rows.reduce((total, row) => total + row.runs, 0)
    const failed = rows.reduce((total, row) => total + row.failed, 0)
    const released = rows.reduce((total, row) => total + row.released, 0)

    return (
        <KpiStrip>
            <Kpi label="Jobs" value={number(rows.length)} />
            <Kpi label={t('Has failures')} value={number(failing)} tone={failing > 0 ? 'fail' : undefined} />
            <Kpi label="Runs" value={number(runs)} />
            <Kpi label={t('Failed runs')} value={number(failed)} tone={failed > 0 ? 'fail' : undefined} />
            <Kpi label="Released" value={number(released)} tone={released > 0 ? 'warn' : undefined} />
        </KpiStrip>
    )
}

function ModeLegend({ mode }) {
    if (mode === 'timeline') {
        return <HeatmapLegend />
    }

    return mode === 'workers' ? null : <JobStripLegend />
}

function Content({ mode, rows, props, settings }) {
    const { dates, timeline, workers, horizon, collapsed_groups: collapsedGroups } = props

    if (mode === 'workers') {
        return <WorkersTable supervisors={workers ?? []} horizon={horizon} />
    }

    if (mode === 'matrix') {
        return <JobMatrix groups={groupJobs(rows, byFailedThenRuns)} dates={dates} showClass={settings.showClass} timelineUrl={timelineUrl} />
    }

    if (mode === 'timeline' && timeline) {
        return (
            <HourHeatmap
                timeline={timeline}
                groups={groupJobs(rows, byFirstRun)}
                showClass={settings.showClass}
                toolbar={<DayPicker date={timeline.date} today={timeline.today} label={timeline.label} onChange={(day) => visit({ day: day === timeline.today ? undefined : day })} />}
            />
        )
    }

    return <JobsList jobs={rows} dates={dates} grouped={settings.grouped} showClass={settings.showClass} collapsedGroups={collapsedGroups ?? []} />
}

function useJobSettings() {
    const [status, setStatus] = useState(DEFAULTS.status)
    const [queue, setQueue] = useState(DEFAULTS.queue)
    const [grouped, setGrouped] = usePersistentState('ops-jobs-grouped', DEFAULTS.grouped)
    const [showClass, setShowClass] = usePersistentState('ops-jobs-show-class', DEFAULTS.showClass)
    const [showSilenced, setShowSilenced] = usePersistentState('ops-jobs-show-silenced', DEFAULTS.showSilenced)
    const setters = { status: setStatus, queue: setQueue, grouped: setGrouped, showClass: setShowClass, showSilenced: setShowSilenced }

    function change(next) {
        Object.entries(next).forEach(([key, value]) => setters[key](value))
    }

    return [{ status, queue, grouped, showClass, showSilenced }, change]
}

export default function Jobs(props) {
    const { mode } = props
    const [search, setSearch] = useState('')
    const [settings, change] = useJobSettings()
    const queueFilter = mode === 'list' ? settings.queue : 'all'

    const all = rowsFor(mode, props)
    const shown = settings.showSilenced ? all : all.filter((row) => !row.silenced)
    const term = search.trim().toLowerCase()
    const matcher = statusFilters().find((filter) => filter.value === settings.status).match
    const visible = shown.filter((row) => matcher(row) && matchesQueue(row, queueFilter) && matchesSearch(row, term))
    const summaryRows = mode === 'workers' ? props.jobs.filter((row) => settings.showSilenced || !row.silenced) : shown

    return (
        <OpsPage title="Jobs" toolbar={<TimeFilter presets={WINDOWED.includes(mode)} />}>
            <div className="space-y-3">
                <Summary rows={summaryRows} />

                <div className="flex flex-wrap items-center gap-2">
                    <Pills options={modes()} value={mode} onChange={(next) => visit({ view: next === 'list' ? undefined : next, day: undefined })} />
                    {mode !== 'workers' && <SearchInput value={search} onChange={setSearch} placeholder={t('Search job / domain / queue…')} />}
                    {mode !== 'workers' && <JobsMenu mode={mode} rows={shown} settings={settings} onChange={change} silencedCount={all.filter((row) => row.silenced).length} />}
                    {queueFilter !== 'all' && (
                        <button type="button" onClick={() => change({ queue: 'all' })} title={t('Clear the queue filter')}>
                            <Badge tone="blue">queue: {queueFilter} ×</Badge>
                        </button>
                    )}
                    <span className="ml-auto">
                        <ModeLegend mode={mode} />
                    </span>
                </div>

                <Content mode={mode} rows={visible} props={props} settings={settings} />
            </div>
        </OpsPage>
    )
}
