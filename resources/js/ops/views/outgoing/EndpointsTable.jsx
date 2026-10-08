import { SparkBars } from '../../charts/SparkBars'
import { Sparkline } from '../../charts/Sparkline'
import { t } from '../../i18n'
import { duration } from '../../lib/chart'
import { cn } from '../../lib/cn'
import { number } from '../../lib/format'
import { Badge } from '../../ui/Badge'
import { DataTable } from '../../ui/DataTable'
import { deviationLabel, SPARK_SEGMENTS } from './shared'

const dark = (endpoint) => endpoint.is_dark

function columns() {
    return [
        {
            key: 'endpoint',
            label: t('Endpoint'),
            className: 'w-full max-w-0 min-w-48 overflow-hidden',
            sortable: true,
            render: (endpoint) => (
                <span className="block min-w-48 truncate font-semibold text-zinc-900 group-hover:text-ops-accent-600" title={endpoint.endpoint}>
                    {endpoint.endpoint}
                </span>
            ),
        },
        {
            key: 'health',
            label: t('Health'),
            sortable: true,
            sortValue: (endpoint) => endpoint.health_rank,
            render: (endpoint) => <Badge tone={endpoint.health_tone}>{t(endpoint.health_label)}</Badge>,
        },
        { key: 'spark', label: t('Outcomes'), render: (endpoint) => <SparkBars series={endpoint.is_dark ? [] : endpoint.spark} segments={SPARK_SEGMENTS} width={80} height={18} /> },
        { key: 'calls', label: t('Calls'), align: 'right', sortable: true, sortDir: 'desc', sortValue: (endpoint) => (dark(endpoint) ? null : endpoint.calls), render: (endpoint) => (dark(endpoint) ? '—' : number(endpoint.calls)) },
        {
            key: 'failed',
            label: t('Failed'),
            align: 'right',
            sortable: true,
            sortDir: 'desc',
            sortValue: (endpoint) => (dark(endpoint) ? null : endpoint.failed),
            render: (endpoint) => <span className={cn(endpoint.failed > 0 ? 'text-ops-fail' : 'text-zinc-400')}>{dark(endpoint) ? '—' : number(endpoint.failed)}</span>,
        },
        {
            key: 'error_pct',
            label: t('Error'),
            align: 'right',
            sortable: true,
            sortDir: 'desc',
            sortValue: (endpoint) => (dark(endpoint) ? null : endpoint.error_pct),
            render: (endpoint) => <span className={cn(endpoint.health === 'incident' && 'font-semibold text-ops-fail')}>{dark(endpoint) ? '—' : `${endpoint.error_pct}%`}</span>,
        },
        {
            key: 'deviation',
            label: <span title={t('Error % in this window minus its own 7-day error %')}>{t('vs normal')}</span>,
            align: 'right',
            sortable: true,
            sortDir: 'desc',
            render: (endpoint) => (
                <span
                    className={cn(endpoint.deviation === null ? 'text-zinc-300' : endpoint.deviation > 3 ? 'text-ops-fail' : 'text-zinc-500')}
                    title={endpoint.baseline_error_pct === null ? undefined : t('7-day baseline :value%', { value: endpoint.baseline_error_pct })}
                >
                    {deviationLabel(endpoint.deviation)}
                </span>
            ),
        },
        {
            key: 'error_days',
            label: t('Error 7 d'),
            render: (endpoint) => <Sparkline points={endpoint.error_days} color={endpoint.error_pct > 3 ? '#dc2626' : '#a8a29e'} width={56} height={18} dot />,
        },
        { key: 'p50', label: 'p50', align: 'right', sortable: true, sortDir: 'desc', render: (endpoint) => duration(endpoint.p50) },
        { key: 'p95', label: 'p95', align: 'right', sortable: true, sortDir: 'desc', render: (endpoint) => duration(endpoint.p95) },
        { key: 'max', label: 'max', align: 'right', sortable: true, sortDir: 'desc', className: 'text-zinc-500', render: (endpoint) => duration(endpoint.max) },
    ]
}

export function EndpointsTable({ endpoints }) {
    return (
        <DataTable
            columns={columns()}
            rows={endpoints}
            rowKey="endpoint"
            rowHref={(endpoint) => endpoint.url}
            defaultSort={{ key: 'health', dir: 'asc' }}
            empty={t('No endpoints match the filter')}
        />
    )
}
