import { ChevronDown, ChevronRight } from 'lucide-react'

import { Badge } from '../../ui/Badge'
import { DataTable } from '../../ui/DataTable'
import { SparkBars } from '../../charts/SparkBars'
import { Sparkline } from '../../charts/Sparkline'
import { t } from '../../i18n'
import { cn } from '../../lib/cn'
import { number } from '../../lib/format'
import { relative } from '../../lib/time'
import { usePersistentState } from '../../lib/usePersistentState'
import { JobStrip } from './JobStrip'
import { groupJobs, healthLabel, healthRank } from './shared'

const bySeverity = (a, b) => healthRank(a) - healthRank(b) || b.failed - a.failed || b.runs - a.runs

function throughputSegments() {
    return [
        { key: 'ok', color: '#d6d3d1', label: t('succeeded') },
        { key: 'timeout', color: '#dc2626', label: 'failed' },
    ]
}

function columns({ dates, showClass }) {
    return [
        {
            key: 'job',
            label: 'Job',
            className: 'w-full max-w-0 min-w-56 overflow-hidden',
            sortable: true,
            render: (job) => (
                <span className="block min-w-56" title={job.job_class}>
                    <span className="flex items-center gap-1.5">
                        <span className="truncate font-semibold text-zinc-900 group-hover:text-ops-accent-600">{job.job}</span>
                        {job.silenced && <span className="shrink-0 rounded-sm bg-zinc-100 px-1 text-[0.625rem] text-zinc-500">silenced</span>}
                    </span>
                    {showClass && job.namespace && <span className="block truncate text-[0.6875rem] text-zinc-400">{job.namespace}</span>}
                </span>
            ),
        },
        {
            key: 'queue',
            label: 'Queue',
            sortable: true,
            render: (job) => (
                <span className="text-zinc-500">
                    <span className="rounded-sm bg-zinc-100 px-1 text-[0.6875rem]">{job.queue}</span>
                    {job.more_queues > 0 && <span className="ml-1 text-[0.6875rem] text-zinc-400">+{job.more_queues}</span>}
                </span>
            ),
        },
        {
            key: 'health',
            label: <span title={t('Failing = failure rate ≥ 20% · Degrading = has failures')}>{t('Status')}</span>,
            sortable: true,
            sortValue: healthRank,
            render: (job) => <Badge tone={job.health_color}>{healthLabel(job)}</Badge>,
        },
        { key: 'history', label: t(':count days', { count: dates.length }), render: (job) => <JobStrip history={job.history} dates={dates} /> },
        { key: 'runs', label: 'Runs', align: 'right', sortable: true, sortDir: 'desc', render: (job) => number(job.runs) },
        {
            key: 'failed',
            label: <span title={t('Failed + timed out · released = put back on the queue to retry; not a failure and not counted in run time')}>Failed</span>,
            align: 'right',
            sortable: true,
            sortDir: 'desc',
            render: (job) => (
                <span className="block leading-tight">
                    <span className={cn(job.failed > 0 ? 'font-semibold text-ops-fail' : 'text-zinc-400')}>{number(job.failed)}</span>
                    {job.released > 0 && <span className="block text-[0.6875rem] text-ops-warn">{t(':count released', { count: number(job.released) })}</span>}
                </span>
            ),
        },
        {
            key: 'fail_pct',
            label: 'Fail %',
            align: 'right',
            sortable: true,
            sortDir: 'desc',
            render: (job) => <span className={cn(job.failed > 0 ? 'text-ops-fail' : 'text-zinc-300')}>{job.failed > 0 ? `${job.fail_pct}%` : '—'}</span>,
        },
        { key: 'spark_outcome', label: 'Throughput', render: (job) => <SparkBars series={job.spark_outcome} segments={throughputSegments()} width={72} height={18} /> },
        { key: 'avg', label: 'avg', align: 'right', sortable: true, sortDir: 'desc', sortValue: (job) => job.avg_ms, className: 'text-zinc-500', render: (job) => job.avg_label },
        {
            key: 'p95',
            label: <span title={t('Run time p95 without released attempts · red = the job is failing')}>p95</span>,
            align: 'right',
            sortable: true,
            sortDir: 'desc',
            sortValue: (job) => job.p95_ms,
            render: (job) => <span className={cn(job.health === 'incident' && 'font-semibold text-ops-fail')}>{job.p95_label}</span>,
        },
        { key: 'spark_p95', label: t('p95 trend'), render: (job) => <Sparkline points={job.spark_p95} color={job.health === 'incident' ? '#dc2626' : '#a8a29e'} width={56} height={18} dot /> },
        {
            key: 'wait',
            label: <span title={t('Time from dispatch until the run started (p95)')}>{t('Queue wait')}</span>,
            align: 'right',
            sortable: true,
            sortDir: 'desc',
            sortValue: (job) => job.wait_p95_ms,
            className: 'text-zinc-500',
            render: (job) => job.wait_p95_label,
        },
        {
            key: 'last_run_at',
            label: t('Last run'),
            align: 'right',
            sortable: true,
            sortDir: 'desc',
            sortValue: (job) => job.last_run_at_iso,
            render: (job) => (
                <span className="text-zinc-500" title={job.last_run_at}>
                    {relative(job.last_run_at_iso) ?? '—'}
                </span>
            ),
        },
    ]
}

function useCollapsed(defaults) {
    const [overrides, setOverrides] = usePersistentState('ops-jobs-collapsed-groups', {})
    const isCollapsed = (group) => overrides[group] ?? defaults.includes(group)

    return { isCollapsed, toggle: (group) => setOverrides({ ...overrides, [group]: !isCollapsed(group) }) }
}

function GroupToggle({ label, collapsed, onToggle }) {
    const Icon = collapsed ? ChevronRight : ChevronDown

    return (
        <button type="button" onClick={onToggle} className="relative z-10 inline-flex items-center gap-1 hover:text-zinc-900" aria-expanded={!collapsed}>
            <Icon className="size-3" />
            {label}
        </button>
    )
}

function domainGroups(jobs, collapsed) {
    return groupJobs(jobs, bySeverity).map((group) => ({
        ...group,
        label: <GroupToggle label={group.label} collapsed={collapsed.isCollapsed(group.key)} onToggle={() => collapsed.toggle(group.key)} />,
        hint: t(':count jobs', { count: group.rows.length }),
        summary: {
            runs: number(group.runs),
            failed: <span className={cn(group.failed > 0 ? 'text-ops-fail' : 'text-zinc-400')}>{number(group.failed)}</span>,
        },
    }))
}

export function JobsList({ jobs, dates, grouped, showClass, collapsedGroups }) {
    const collapsed = useCollapsed(collapsedGroups)
    const table = {
        columns: columns({ dates, showClass }),
        rowKey: 'job_class',
        rowHref: (job) => job.url,
        empty: t('No job matches the filter'),
    }

    if (!grouped) {
        return <DataTable {...table} rows={[...jobs].sort(bySeverity)} />
    }

    return <DataTable {...table} groups={domainGroups(jobs, collapsed)} rowClassName={(job) => (collapsed.isCollapsed(job.group) ? 'hidden' : undefined)} />
}
