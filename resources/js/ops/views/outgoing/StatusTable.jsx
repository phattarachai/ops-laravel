import { t } from '../../i18n'
import { duration } from '../../lib/chart'
import { number } from '../../lib/format'
import { Badge } from '../../ui/Badge'
import { DataTable } from '../../ui/DataTable'
import { outcomeLabels } from './shared'

const BADGE = { ok: 'green', warn: 'amber', fail: 'red' }

function columns() {
    const labels = outcomeLabels()

    return [
        { key: 'method', label: t('Method'), sortable: true, className: 'font-mono text-[0.6875rem]' },
        { key: 'status', label: t('Status'), sortable: true, render: (row) => (row.status === 0 ? <span className="text-zinc-500">{t('No response')}</span> : row.status) },
        { key: 'outcome', label: t('Outcome'), sortable: true, render: (row) => <Badge tone={BADGE[row.tone]}>{labels[row.outcome] ?? row.outcome}</Badge> },
        { key: 'calls', label: t('Calls'), align: 'right', sortable: true, sortDir: 'desc', render: (row) => number(row.calls) },
        { key: 'share', label: t('Share'), align: 'right', className: 'text-zinc-500' },
        { key: 'p50', label: 'p50', align: 'right', sortable: true, sortDir: 'desc', render: (row) => duration(row.p50) },
        { key: 'p95', label: 'p95', align: 'right', sortable: true, sortDir: 'desc', render: (row) => duration(row.p95) },
        { key: 'max', label: 'max', align: 'right', sortable: true, sortDir: 'desc', className: 'text-zinc-500', render: (row) => duration(row.max) },
    ]
}

export function StatusTable({ statuses }) {
    return <DataTable columns={columns()} rows={statuses} rowKey="key" flush empty={t('No calls in this window')} />
}
