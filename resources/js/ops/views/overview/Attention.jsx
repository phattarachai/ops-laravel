import { useState } from 'react'

import { t } from '../../i18n'
import { Badge, DataTable, Section } from '../../ui'

const CAP = 12

function columns() {
    return [
        { key: 'area', label: t('Area'), className: 'text-zinc-500', render: (row) => t(row.area) },
        { key: 'name', label: t('Name'), className: 'min-w-40 max-w-80 overflow-hidden font-medium', render: (row) => <span className="block truncate">{row.name}</span> },
        { key: 'label', label: t('Status'), render: (row) => <Badge tone={row.tone}>{t(row.label)}</Badge> },
        { key: 'detail', label: t('Detail'), align: 'right', className: 'tabular-nums' },
    ]
}

export function Attention({ rows, windowLabel }) {
    const [showAll, setShowAll] = useState(false)
    const shown = showAll ? rows : rows.slice(0, CAP)

    return (
        <Section
            title={t('Needs attention')}
            hint={windowLabel}
            flush
            actions={
                rows.length > CAP && (
                    <button type="button" onClick={() => setShowAll(!showAll)} className="text-[0.6875rem] text-ops-accent-600 hover:underline">
                        {showAll ? t('Show less') : t('Show all (:count)', { count: rows.length })}
                    </button>
                )
            }
        >
            <DataTable columns={columns()} rows={shown} rowKey={(row) => `${row.url}|${row.name}`} rowHref={(row) => row.url} empty={t('Nothing needs attention')} flush />
        </Section>
    )
}
