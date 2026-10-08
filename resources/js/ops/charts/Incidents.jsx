import { DataTable } from '../ui'
import { t } from '../i18n'

const columnsFor = () => [
    { key: 'at', label: t('Started'), className: 'text-zinc-500' },
    { key: 'duration', label: t('Lasted'), align: 'right' },
    { key: 'count', label: t('Times'), align: 'right', className: 'font-semibold text-ops-fail', render: (incident) => `×${incident.count}` },
    { key: 'exception', label: 'Exception', className: 'text-ops-fail' },
    { key: 'message', label: 'Message', truncate: true },
    {
        key: 'domains',
        label: 'Domain',
        className: 'text-zinc-500',
        render: (incident) => [incident.domains.join(' · '), incident.more_domains > 0 ? `+${incident.more_domains}` : null].filter(Boolean).join(' · '),
    },
]

export function Incidents({ incidents, empty = t('No failures'), showDomains = true }) {
    const columns = showDomains ? columnsFor() : columnsFor().filter((column) => column.key !== 'domains')

    return <DataTable columns={columns} rows={incidents} rowKey={(incident) => `${incident.at}-${incident.exception}`} empty={empty} flush />
}
