import { t } from '../../i18n'
import { relative } from '../../lib/time'
import { number } from '../../lib/format'
import { DataTable } from '../../ui/DataTable'
import { SeverityBadge, StatusBadge } from './shared'

const SEVERITY_RANK = { critical: 0, warning: 1 }

function columns() {
    return [
        {
            key: 'rule_name',
            label: t('Rule'),
            className: 'w-full max-w-0 min-w-56 overflow-hidden',
            sortable: true,
            render: (incident) => (
                <span className="block min-w-56">
                    <span className="block truncate font-semibold text-zinc-900 group-hover:text-ops-accent-600">{incident.rule_name ?? t('Deleted rule')}</span>
                    <span className="block truncate text-[0.6875rem] text-zinc-500" title={incident.key}>
                        {incident.key === '' ? '—' : incident.key}
                    </span>
                </span>
            ),
        },
        { key: 'status', label: t('Status'), sortable: true, render: (incident) => <StatusBadge open={incident.is_open} /> },
        { key: 'severity', label: t('Severity'), sortable: true, sortValue: (incident) => SEVERITY_RANK[incident.severity] ?? 9, render: (incident) => <SeverityBadge severity={incident.severity} /> },
        {
            key: 'opened_iso',
            label: t('Opened'),
            sortable: true,
            sortDir: 'desc',
            render: (incident) => <span title={relative(incident.opened_iso)}>{incident.opened}</span>,
        },
        {
            key: 'last_breach_iso',
            label: t('Last breach'),
            sortable: true,
            sortDir: 'desc',
            className: 'text-zinc-500',
            render: (incident) => <span title={incident.last_breach}>{relative(incident.last_breach_iso)}</span>,
        },
        { key: 'duration_seconds', label: t('Lasted'), align: 'right', sortable: true, sortDir: 'desc', render: (incident) => incident.duration },
        { key: 'peak_value', label: t('Peak'), align: 'right', sortable: true, sortDir: 'desc', render: (incident) => incident.peak },
        { key: 'notifications', label: t('Notified'), align: 'right', sortable: true, sortDir: 'desc', className: 'text-zinc-500', render: (incident) => number(incident.notifications) },
    ]
}

export function IncidentsTable({ incidents }) {
    return <DataTable columns={columns()} rows={incidents} rowHref={(incident) => incident.url} empty={t('No incidents match the filter')} />
}
