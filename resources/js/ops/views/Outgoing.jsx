import { useState } from 'react'

import { Kpi, KpiStrip } from '../charts/Kpi'
import { Legend } from '../charts/Legend'
import { TimeFilter } from '../charts/TimeFilter'
import { t } from '../i18n'
import { percent } from '../lib/chart'
import { number } from '../lib/format'
import { OpsPage } from '../shell/OpsPage'
import { SearchInput } from '../ui/SearchInput'
import { EndpointsTable } from './outgoing/EndpointsTable'
import { FilterMenu } from './outgoing/FilterMenu'
import { healthFilters, sparkLegend } from './outgoing/shared'

function Summary({ summary }) {
    return (
        <KpiStrip>
            <Kpi label={t('Endpoints')} value={number(summary.endpoints)} />
            <Kpi label={t('Unhealthy')} value={number(summary.unhealthy)} tone={summary.unhealthy > 0 ? 'fail' : undefined} />
            <Kpi label={t('Calls')} value={number(summary.calls)} />
            <Kpi label={t('Failed')} value={number(summary.failed)} tone={summary.failed > 0 ? 'fail' : undefined} />
            <Kpi label={t('Error')} value={summary.calls > 0 ? percent(summary.failed, summary.calls) : '—'} />
        </KpiStrip>
    )
}

export default function Outgoing({ summary, endpoints }) {
    const [search, setSearch] = useState('')
    const [health, setHealth] = useState('all')

    const term = search.trim().toLowerCase()
    const healthMatch = healthFilters().find((filter) => filter.value === health).match
    const visible = endpoints.filter((endpoint) => healthMatch(endpoint) && (term === '' || endpoint.endpoint.toLowerCase().includes(term)))

    return (
        <OpsPage title="Outgoing" toolbar={<TimeFilter />}>
            <div className="space-y-3">
                <Summary summary={summary} />

                <div className="flex flex-wrap items-center gap-2">
                    <SearchInput value={search} onChange={setSearch} placeholder={t('Search endpoint…')} />
                    <FilterMenu endpoints={endpoints} health={health} onChange={setHealth} />
                    <span className="ml-auto">
                        <Legend items={sparkLegend()} />
                    </span>
                </div>

                <EndpointsTable endpoints={visible} />
            </div>
        </OpsPage>
    )
}
