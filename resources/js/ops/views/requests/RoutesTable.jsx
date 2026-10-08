import { SparkBars } from '../../charts/SparkBars'
import { t } from '../../i18n'
import { duration } from '../../lib/chart'
import { cn } from '../../lib/cn'
import { number } from '../../lib/format'
import { DataTable } from '../../ui/DataTable'
import { RouteName } from './RouteName'
import { countTone, deltaLabel, STATUS_SPARK_SEGMENTS } from './shared'
import { StatusHeading } from './StatusHeading'

const DEFAULT_SORT = { key: 'requests', dir: 'desc' }

const sum = (rows, key) => rows.reduce((total, row) => total + row[key], 0)

function statusColumn(key) {
    return {
        key,
        label: <StatusHeading status={key} />,
        align: 'right',
        sortable: true,
        sortDir: 'desc',
        render: (route) => <span className={countTone(key, route[key])}>{number(route[key])}</span>,
    }
}

function columns({ showName }) {
    return [
        {
            key: 'route',
            label: t('Url'),
            className: 'w-full max-w-0 min-w-64 overflow-hidden',
            sortable: true,
            sortValue: (route) => route.uri ?? route.route,
            render: (route) => (
                <span className="block min-w-64 truncate" title={route.route}>
                    <RouteName route={route.route} uri={route.uri} methods={route.methods} showName={showName} />
                </span>
            ),
        },
        { key: 'requests', label: t('Requests'), align: 'right', sortable: true, sortDir: 'desc', render: (route) => number(route.requests) },
        {
            key: 'delta',
            label: <span title={t('Against the same window 7 days ago')}>{t('vs 7 days ago')}</span>,
            align: 'right',
            render: (route) => (
                <span className="text-zinc-500" title={t('7 days ago: :count requests', { count: number(route.last_week) })}>
                    {deltaLabel(route.requests, route.delta)}
                </span>
            ),
        },
        statusColumn('s4xx'),
        statusColumn('s429'),
        statusColumn('s5xx'),
        { key: 'spark', label: t('Status'), render: (route) => <SparkBars series={route.spark} segments={STATUS_SPARK_SEGMENTS} width={80} height={18} /> },
        { key: 'p50', label: 'p50', align: 'right', className: 'text-zinc-500', sortable: true, sortDir: 'desc', render: (route) => route.p50_label },
        { key: 'p95', label: 'p95', align: 'right', sortable: true, sortDir: 'desc', render: (route) => <span title={`max ${duration(route.max)}`}>{route.p95_label}</span> },
    ]
}

function groupRoutes(routes) {
    const groups = new Map()

    routes.forEach((route) => groups.set(route.group, [...(groups.get(route.group) ?? []), route]))

    return [...groups]
        .map(([group, rows]) => ({
            key: group,
            label: group || '—',
            hint: t(':count routes', { count: rows.length }),
            rows,
            total: sum(rows, 'requests'),
            summary: {
                requests: number(sum(rows, 'requests')),
                s4xx: number(sum(rows, 's4xx')),
                s429: <span className={cn(sum(rows, 's429') > 0 && 'text-ops-warn')}>{number(sum(rows, 's429'))}</span>,
                s5xx: <span className={cn(sum(rows, 's5xx') > 0 && 'text-ops-fail')}>{number(sum(rows, 's5xx'))}</span>,
            },
        }))
        .sort((a, b) => b.total - a.total)
}

export function RoutesTable({ routes, grouped, showName }) {
    const shared = { columns: columns({ showName }), rowKey: 'route', rowHref: (route) => route.url, defaultSort: DEFAULT_SORT, empty: t('No route matches the filter') }

    return grouped ? <DataTable {...shared} groups={groupRoutes(routes)} /> : <DataTable {...shared} rows={routes} />
}
