import { SlidersHorizontal, X } from 'lucide-react'
import { useState } from 'react'

import { Kpi, KpiStrip } from '../charts/Kpi'
import { t } from '../i18n'
import { number } from '../lib/format'
import { OpsPage } from '../shell/OpsPage'
import { Badge } from '../ui/Badge'
import { Dropdown, DropdownOption, DropdownSection } from '../ui/Dropdown'
import { Pills } from '../ui/Pills'
import { SearchInput } from '../ui/SearchInput'
import { IncidentsTable } from './incidents/IncidentsTable'

const STATUS_MATCH = {
    all: () => true,
    open: (incident) => incident.is_open,
    resolved: (incident) => !incident.is_open,
}

function severityOptions() {
    return [
        { value: 'all', label: t('All severities') },
        { value: 'critical', label: t('Critical') },
        { value: 'warning', label: t('Warning') },
    ]
}

function SeverityMenu({ incidents, severity, onChange }) {
    const options = severityOptions()

    return (
        <>
            <Dropdown icon={SlidersHorizontal} title={t('Filter')} active={severity !== 'all'}>
                {({ close }) => (
                    <DropdownSection title={t('Severity')}>
                        {options.map((option) => (
                            <DropdownOption
                                key={option.value}
                                selected={severity === option.value}
                                hint={option.value === 'all' ? incidents.length : incidents.filter((incident) => incident.severity === option.value).length}
                                onSelect={() => {
                                    close()
                                    onChange(option.value)
                                }}
                            >
                                {option.label}
                            </DropdownOption>
                        ))}
                    </DropdownSection>
                )}
            </Dropdown>
            {severity !== 'all' && (
                <Badge>
                    {options.find((option) => option.value === severity).label}
                    <button type="button" onClick={() => onChange('all')} aria-label={t('Clear filter')} className="text-zinc-400 hover:text-zinc-700">
                        <X className="size-3" />
                    </button>
                </Badge>
            )}
        </>
    )
}

export default function Incidents({ incidents, counts }) {
    const [status, setStatus] = useState(counts.open > 0 ? 'open' : 'all')
    const [severity, setSeverity] = useState('all')
    const [search, setSearch] = useState('')

    const term = search.trim().toLowerCase()
    const visible = incidents.filter(
        (incident) =>
            STATUS_MATCH[status](incident) &&
            (severity === 'all' || incident.severity === severity) &&
            (term === '' || `${incident.rule_name ?? ''} ${incident.key}`.toLowerCase().includes(term)),
    )
    const views = [
        { value: 'open', label: t('Open'), count: counts.open },
        { value: 'resolved', label: t('Resolved'), count: counts.resolved },
        { value: 'all', label: t('All'), count: incidents.length },
    ]

    return (
        <OpsPage title="Incidents">
            <div className="space-y-3">
                <KpiStrip>
                    <Kpi label={t('Open')} value={number(counts.open)} tone={counts.open > 0 ? 'fail' : undefined} />
                    <Kpi label={t('Open critical')} value={number(counts.critical)} tone={counts.critical > 0 ? 'fail' : undefined} />
                    <Kpi label={t('Resolved')} value={number(counts.resolved)} />
                </KpiStrip>

                <div className="flex flex-wrap items-center gap-2">
                    <Pills options={views} value={status} onChange={setStatus} />
                    <SearchInput value={search} onChange={setSearch} placeholder={t('Search rule / key…')} />
                    <SeverityMenu incidents={incidents} severity={severity} onChange={setSeverity} />
                </div>

                <IncidentsTable incidents={visible} />
            </div>
        </OpsPage>
    )
}
