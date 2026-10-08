import { Kpi, KpiStrip, Sparkline, TimeFilter } from '../charts'
import { t } from '../i18n'
import { number } from '../lib/format'
import { OpsPage } from '../shell/OpsPage'
import { DataTable, Section, SectionGrid, SectionStack } from '../ui'

function percent(value) {
    return value === null || value === undefined ? '—' : `${value}%`
}

function queryColumns() {
    return [
        {
            key: 'sql',
            label: 'SQL',
            className: 'w-full max-w-0 min-w-64 overflow-hidden',
            render: (row) => (
                <div className="min-w-0">
                    <div className="truncate font-mono text-[0.6875rem]" title={row.sql}>
                        {row.sql}
                    </div>
                    {row.location && <div className="truncate text-[0.625rem] text-zinc-400">{row.location}</div>}
                </div>
            ),
        },
        { key: 'count', label: t('Count'), align: 'right', sortable: true, render: (row) => number(row.count) },
        { key: 'avg_label', label: t('Avg'), align: 'right' },
        { key: 'max_label', label: t('Max'), align: 'right', className: 'text-ops-warn' },
    ]
}

function cacheColumns() {
    return [
        { key: 'key', label: t('Key pattern'), className: 'w-full max-w-0 min-w-48 overflow-hidden', truncate: true },
        { key: 'hits', label: t('Hits'), align: 'right', sortable: true, render: (row) => number(row.hits) },
        { key: 'misses', label: t('Misses'), align: 'right', sortable: true, render: (row) => number(row.misses) },
        { key: 'rate', label: t('Hit rate'), align: 'right' },
    ]
}

function queueColumns() {
    return [
        { key: 'name', label: t('Queue'), className: 'min-w-32' },
        { key: 'size', label: t('Now'), align: 'right', sortable: true, render: (row) => number(row.size) },
        { key: 'delayed', label: t('Delayed'), align: 'right', render: (row) => number(row.delayed) },
        { key: 'reserved', label: t('Running'), align: 'right', render: (row) => number(row.reserved) },
        { key: 'oldest_wait', label: t('Oldest wait'), align: 'right' },
        { key: 'peak', label: t('Peak'), align: 'right', sortable: true, render: (row) => number(row.peak) },
        { key: 'series', label: t('Depth'), render: (row) => <Sparkline points={row.series} fill /> },
    ]
}

function serverColumns() {
    return [
        { key: 'host', label: t('Server'), className: 'min-w-32' },
        { key: 'load', label: t('Load'), align: 'right', render: (row) => (row.load === null ? '—' : `${row.load}${row.cpus ? ` / ${row.cpus}` : ''}`) },
        { key: 'memory', label: t('Memory'), align: 'right', render: (row) => percent(row.memory) },
        { key: 'disk', label: t('Disk'), align: 'right', render: (row) => percent(row.disk) },
        { key: 'memory_series', label: t('Memory'), render: (row) => <Sparkline points={row.memory_series} fill /> },
        { key: 'seen', label: t('Last seen'), align: 'right', className: 'text-zinc-500' },
    ]
}

function exceptionColumns() {
    return [
        {
            key: 'class',
            label: t('Exception'),
            className: 'w-full max-w-0 min-w-48 overflow-hidden',
            render: (row) => (
                <div className="min-w-0">
                    <div className="truncate text-ops-fail" title={row.class}>
                        {row.class.split('\\').pop()}
                    </div>
                    <div className="truncate text-[0.625rem] text-zinc-400">{row.locations.join(' · ')}</div>
                </div>
            ),
        },
        { key: 'count', label: t('Count'), align: 'right', sortable: true, render: (row) => number(row.count) },
        { key: 'series', label: t('Trend'), render: (row) => <Sparkline points={row.series} color="#dc2626" /> },
        { key: 'last', label: t('Last'), align: 'right', className: 'text-zinc-500' },
    ]
}

function deployColumns() {
    return [
        { key: 'release', label: t('Release'), className: 'font-mono' },
        { key: 'description', label: t('Note'), truncate: true },
        { key: 'at', label: t('Deployed'), align: 'right', className: 'text-zinc-500' },
    ]
}

export default function System({ queries, cache, queues, servers, exceptions, deploys }) {
    const backlog = queues.reduce((total, queue) => total + (queue.size ?? 0), 0)

    return (
        <OpsPage title="System" toolbar={<TimeFilter />}>
            <SectionStack>
                <KpiStrip>
                    <Kpi label={t('Slow queries')} value={number(queries.reduce((total, row) => total + row.count, 0))} tone={queries.length > 0 ? 'warn' : undefined} />
                    <Kpi label={t('Cache hit rate')} value={cache.rate} />
                    <Kpi label={t('Queued now')} value={number(backlog)} tone={backlog > 0 ? 'warn' : undefined} />
                    <Kpi label={t('Exceptions')} value={number(exceptions.reduce((total, row) => total + row.count, 0))} tone={exceptions.length > 0 ? 'fail' : undefined} />
                    <Kpi label={t('Deploys')} value={number(deploys.length)} hint={deploys[0]?.release} />
                </KpiStrip>

                <SectionGrid>
                    <Section title={t('Queues')} flush>
                        <DataTable columns={queueColumns()} rows={queues} rowKey="name" flush />
                    </Section>
                    <Section title={t('Servers')} flush>
                        <DataTable columns={serverColumns()} rows={servers} rowKey="host" flush />
                    </Section>
                </SectionGrid>

                <Section title={t('Slow queries')} flush>
                    <DataTable columns={queryColumns()} rows={queries} rowKey="hash" flush />
                </Section>

                <SectionGrid>
                    <Section title={t('Exceptions')} flush>
                        <DataTable columns={exceptionColumns()} rows={exceptions} rowKey="class" flush />
                    </Section>
                    <Section title={t('Cache')} hint={cache.rate} flush>
                        <DataTable columns={cacheColumns()} rows={cache.keys} rowKey="key" flush />
                    </Section>
                </SectionGrid>

                <Section title={t('Deploys')} flush>
                    <DataTable columns={deployColumns()} rows={deploys} rowKey={(row) => `${row.release}-${row.at}`} flush />
                </Section>
            </SectionStack>
        </OpsPage>
    )
}
