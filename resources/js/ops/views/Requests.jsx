import { useState } from 'react'

import { Kpi, KpiStrip } from '../charts/Kpi'
import { Legend } from '../charts/Legend'
import { TimeFilter } from '../charts/TimeFilter'
import { t } from '../i18n'
import { number } from '../lib/format'
import { usePersistentState } from '../lib/usePersistentState'
import { OpsPage } from '../shell/OpsPage'
import { Pills } from '../ui/Pills'
import { SearchInput } from '../ui/SearchInput'
import { HotUsers } from './requests/HotUsers'
import { RecordingNotice } from './requests/RecordingNotice'
import { DEFAULTS, RoutesMenu, statusFilters } from './requests/RoutesMenu'
import { RoutesTable } from './requests/RoutesTable'
import { SegmentMenu } from './requests/SegmentMenu'
import { deltaLabel, STATUS_LEGEND, visit } from './requests/shared'
import { UserPanel } from './requests/UserPanel'
import { UsersTable } from './requests/UsersTable'

function Summary({ summary }) {
    return (
        <KpiStrip>
            <Kpi label={t('Requests')} value={number(summary.requests)} />
            <Kpi label={t('vs 7 days ago')} value={<span title={t('7 days ago: :count requests', { count: number(summary.last_week) })}>{deltaLabel(summary.requests, summary.delta)}</span>} />
            <Kpi label="4xx" value={number(summary.s4xx)} />
            <Kpi label="429" value={number(summary.s429)} tone={summary.s429 > 0 ? 'warn' : undefined} />
            <Kpi label="5xx" value={number(summary.s5xx)} tone={summary.s5xx > 0 ? 'fail' : undefined} />
            <Kpi label="p50 / p95" value={`${summary.p50_label} / ${summary.p95_label}`} />
            <Kpi label={t('Users')} value={number(summary.users)} />
        </KpiStrip>
    )
}

const matches = (text, term) => term === '' || text.toLowerCase().includes(term)

const requestsOf = (routes) => routes.reduce((total, route) => total + route.requests, 0)

function useRouteSettings() {
    const [status, setStatus] = useState(DEFAULTS.status)
    const [grouped, setGrouped] = usePersistentState('ops-requests-grouped', DEFAULTS.grouped)
    const [showName, setShowName] = usePersistentState('ops-requests-show-route-name', DEFAULTS.showName)
    const [showUnmatched, setShowUnmatched] = usePersistentState('ops-requests-show-unmatched', DEFAULTS.showUnmatched)
    const setters = { status: setStatus, grouped: setGrouped, showName: setShowName, showUnmatched: setShowUnmatched }

    function change(next) {
        Object.entries(next).forEach(([key, value]) => setters[key](value))
    }

    return [{ status, grouped, showName, showUnmatched }, change]
}

export default function Requests({ tab, summary, routes, users, hot_users, threshold, recording, selected_user, time_filter, user_filters, segment_options }) {
    const [search, setSearch] = useState('')
    const [settings, change] = useRouteSettings()
    const views = [
        { value: 'routes', label: t('Routes') },
        { value: 'users', label: t('Users') },
    ]

    const term = search.trim().toLowerCase()
    const unmatchedRequests = requestsOf(routes.filter((route) => route.noise))
    const shownRoutes = settings.showUnmatched ? routes : routes.filter((route) => !route.noise)
    const statusMatch = statusFilters().find((filter) => filter.value === settings.status).match
    const visibleRoutes = shownRoutes.filter((route) => statusMatch(route) && matches(`${route.route} ${route.uri ?? ''} ${route.methods}`, term))
    const visibleUsers = users.filter((user) => matches(`${user.name} ${user.detail ?? ''} ${user.segment}`, term))

    return (
        <OpsPage title="Incoming" toolbar={<TimeFilter />}>
            <div className="space-y-3">
                <RecordingNotice recording={recording} />
                <Summary summary={summary} />
                <UserPanel user={selected_user} windowLabel={time_filter.label} threshold={threshold} />
                <HotUsers users={hot_users} threshold={threshold} />

                <div className="flex flex-wrap items-center gap-2">
                    <Pills options={views} value={tab} onChange={(next) => visit({ tab: next === 'routes' ? undefined : next })} />
                    <SearchInput value={search} onChange={setSearch} placeholder={tab === 'users' ? t('Search users…') : t('Search route / URI…')} />
                    {tab === 'users' ? (
                        <SegmentMenu filters={user_filters} options={segment_options} />
                    ) : (
                        <RoutesMenu routes={shownRoutes} settings={settings} onChange={change} unmatchedRequests={unmatchedRequests} />
                    )}
                    {tab === 'routes' && (
                        <span className="ml-auto">
                            <Legend items={STATUS_LEGEND} />
                        </span>
                    )}
                </div>

                {tab === 'users' ? <UsersTable users={visibleUsers} selectedId={selected_user?.id} /> : <RoutesTable routes={visibleRoutes} grouped={settings.grouped} showName={settings.showName} />}
            </div>
        </OpsPage>
    )
}
