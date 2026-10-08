import { t } from '../../i18n'
import { cn } from '../../lib/cn'
import { number } from '../../lib/format'
import { DataTable } from '../../ui/DataTable'

function columns() {
    return [
        {
            key: 'queue',
            label: <span title={t('#1 = the queue the supervisor’s workers pull from first')}>Queue</span>,
            className: 'w-56',
            render: (row) => (
                <span className="inline-flex items-center gap-1.5">
                    {row.priority !== null && <span className={cn('rounded-sm px-1 text-[0.625rem] tabular-nums', row.priority === 1 ? 'bg-zinc-800 text-white' : 'bg-zinc-100 text-zinc-500')}>#{row.priority}</span>}
                    <span className="font-semibold text-zinc-900">{row.queue}</span>
                </span>
            ),
        },
        { key: 'runs', label: 'Runs', align: 'right', sortable: true, sortDir: 'desc', render: (row) => (row.runs > 0 ? number(row.runs) : <span className="text-zinc-300">0</span>) },
        { key: 'failed', label: 'Failed', align: 'right', sortable: true, sortDir: 'desc', render: (row) => <span className={cn(row.failed > 0 ? 'font-semibold text-ops-fail' : 'text-zinc-400')}>{number(row.failed)}</span> },
        { key: 'released', label: 'Released', align: 'right', sortable: true, sortDir: 'desc', render: (row) => <span className={cn(row.released > 0 ? 'text-ops-warn' : 'text-zinc-400')}>{number(row.released)}</span> },
        {
            key: 'wait_p95',
            label: <span title={t('Time from dispatch until the run started (p95), from finished runs — not the live queue depth (see Horizon)')}>{t('Queue wait p95')}</span>,
            align: 'right',
            sortable: true,
            sortDir: 'desc',
            sortValue: (row) => row.wait_p95_seconds,
        },
        { key: 'p95', label: t('Run p95'), align: 'right', sortable: true, sortDir: 'desc', sortValue: (row) => row.p95_seconds },
        {
            key: 'jobs',
            label: t('Jobs on this queue'),
            className: 'w-full max-w-0 min-w-48 overflow-hidden',
            render: (row) =>
                row.jobs > 0 ? (
                    <span className="block truncate" title={row.top_jobs.join(', ')}>
                        <span className="text-zinc-700">{t(':count jobs', { count: row.jobs })}</span>
                        <span className="ml-1.5 text-zinc-400">
                            {row.top_jobs.join(', ')}
                            {row.jobs > row.top_jobs.length && ', …'}
                        </span>
                    </span>
                ) : (
                    <span className="text-zinc-300">{t('No jobs in this window')}</span>
                ),
        },
    ]
}

function groupLabel(supervisor, horizon) {
    if (supervisor.name === null) {
        const hint = horizon ? t('Queues that ran jobs but are not in this environment’s Horizon config (e.g. the sync connection)') : t('Horizon is not installed — queues are listed without supervisors')

        return { label: <span title={hint}>{horizon ? t('No supervisor drains these') : t('Queues')}</span> }
    }

    return {
        label: supervisor.name,
        hint: `${supervisor.connection} · ${t(':count processes', { count: supervisor.max_processes })} · timeout ${supervisor.timeout}s · balance ${supervisor.balance}`,
    }
}

export function WorkersTable({ supervisors, horizon }) {
    const groups = supervisors.map((supervisor) => ({ key: supervisor.name ?? 'orphans', ...groupLabel(supervisor, horizon), rows: supervisor.queues }))

    return (
        <div className="space-y-2">
            {!horizon && <p className="rounded border border-zinc-200 bg-zinc-50 px-2.5 py-1.5 text-zinc-600">{t('Horizon is not installed — queues are listed without supervisors')}</p>}
            <DataTable columns={columns()} groups={groups} rowKey="queue" empty={t('No queue ran a job in this window')} />
        </div>
    )
}
