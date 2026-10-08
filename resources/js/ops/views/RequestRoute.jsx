import { Link } from '@inertiajs/react'
import { ChevronLeft } from 'lucide-react'

import { Histogram } from '../charts/chart/Histogram'
import { RangeBars } from '../charts/chart/RangeBars'
import { STATUS_SEGMENTS, StackedBars } from '../charts/chart/StackedBars'
import { Kpi, KpiStrip } from '../charts/Kpi'
import { Legend } from '../charts/Legend'
import { TimeFilter } from '../charts/TimeFilter'
import { t } from '../i18n'
import { number } from '../lib/format'
import { OpsPage } from '../shell/OpsPage'
import { Badge } from '../ui/Badge'
import { Section } from '../ui/Section'
import { RecordingNotice } from './requests/RecordingNotice'
import { RouteName } from './requests/RouteName'
import { SegmentMenu } from './requests/SegmentMenu'
import { countTone, deltaLabel, STATUS_CHART_LEGEND } from './requests/shared'
import { UserPanel } from './requests/UserPanel'
import { UsersTable } from './requests/UsersTable'

function Heading({ route, backUrl }) {
    return (
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <Link href={backUrl} className="inline-flex items-center gap-0.5 text-[0.6875rem] text-zinc-500 hover:text-zinc-800">
                <ChevronLeft className="size-3" />
                {t('Incoming')}
            </Link>
            <h1 className="text-sm">
                <RouteName route={route.name} uri={route.uri} methods={route.methods} showName />
            </h1>
            {route.noise && <Badge>{t('noise')}</Badge>}
        </div>
    )
}

function Summary({ summary, today }) {
    const todayTitle = t('Today :today · same weekday last week :last_week — closed hours only', { today: number(today.today), last_week: number(today.last_week) })

    return (
        <KpiStrip>
            <Kpi label={t('Requests')} value={number(summary.requests)} />
            <Kpi label={t('vs 7 days ago')} value={<span title={t('7 days ago: :count requests', { count: number(summary.last_week) })}>{deltaLabel(summary.requests, summary.delta)}</span>} />
            <Kpi label={t('Today vs last week')} value={<span title={todayTitle}>{deltaLabel(today.today, today.delta)}</span>} />
            <Kpi label="4xx" value={number(summary.s4xx)} />
            <Kpi label="429" value={number(summary.s429)} tone={summary.s429 > 0 ? 'warn' : undefined} />
            <Kpi label="5xx" value={number(summary.s5xx)} tone={summary.s5xx > 0 ? 'fail' : undefined} />
            <Kpi label="p50 / p95" value={`${summary.p50_label} / ${summary.p95_label}`} />
        </KpiStrip>
    )
}

function Segments({ rows }) {
    return (
        <table className="w-full text-xs tabular-nums">
            <thead>
                <tr className="text-[0.6875rem] text-zinc-500">
                    <th className="px-2.5 py-1 text-left font-semibold">{t('Segment')}</th>
                    <th className="px-2 py-1 text-right font-semibold">{t('Requests')}</th>
                    <th className="px-2 py-1 text-right font-semibold">429</th>
                    <th className="px-2 py-1 text-right font-semibold">5xx</th>
                    <th className="px-2.5 py-1 text-right font-semibold">p95</th>
                </tr>
            </thead>
            <tbody className="text-zinc-700">
                {rows.map((row) => (
                    <tr key={row.segment} className="border-t border-zinc-100">
                        <td className="h-7 px-2.5">{row.segment || '—'}</td>
                        <td className="px-2 text-right">{number(row.requests)}</td>
                        <td className={`px-2 text-right ${countTone('s429', row.s429)}`}>{number(row.s429)}</td>
                        <td className={`px-2 text-right ${countTone('s5xx', row.s5xx)}`}>{number(row.s5xx)}</td>
                        <td className="px-2.5 text-right text-zinc-500">{row.p95_label}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    )
}

function Charts({ buckets, segments, histogram, windowLabel }) {
    return (
        <>
            <div className="grid gap-3 xl:grid-cols-3">
                <Section title={t('Requests')} hint={windowLabel} tooltip={t('By status')} className="xl:col-span-2" actions={<Legend items={STATUS_CHART_LEGEND} />}>
                    <StackedBars buckets={buckets} segments={STATUS_SEGMENTS} />
                </Section>
                <Section title={t('Segments')} tooltip={t('Requests by user segment')} flush>
                    <Segments rows={segments} />
                </Section>
            </div>

            <div className="grid gap-3 xl:grid-cols-3">
                <Section
                    title={t('Latency')}
                    hint={windowLabel}
                    className="xl:col-span-2"
                    actions={
                        <Legend
                            items={[
                                { tone: 'ink', label: 'p50 → p95' },
                                { tone: 'ink', label: 'max', shape: 'line' },
                                { tone: 'fail', label: '5xx', shape: 'dot' },
                            ]}
                        />
                    }
                >
                    <RangeBars buckets={buckets} />
                </Section>
                <Section title={t('Distribution')} hint={windowLabel} tooltip={t('Response time')}>
                    <Histogram bands={histogram} unit={t('requests')} />
                </Section>
            </div>
        </>
    )
}

export default function RequestRoute({ route, back_url, summary, today, recording, threshold, selected_user, time_filter, buckets, segment_split, histogram, users, user_filters, segment_options }) {
    return (
        <OpsPage title={route.uri ?? route.name} toolbar={<TimeFilter />}>
            <div className="space-y-3">
                <Heading route={route} backUrl={back_url} />
                <RecordingNotice recording={recording} />
                <UserPanel user={selected_user} windowLabel={time_filter.label} threshold={threshold} />

                {summary ? (
                    <>
                        <Summary summary={summary} today={today} />
                        <Charts buckets={buckets} segments={segment_split} histogram={histogram} windowLabel={time_filter.label} />
                    </>
                ) : (
                    <div className="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">{t('No requests to this route in :window', { window: time_filter.label })}</div>
                )}

                <Section title={t('Top users')} flush actions={<SegmentMenu filters={user_filters} options={segment_options} />}>
                    <UsersTable users={users} selectedId={selected_user?.id} flush />
                </Section>
            </div>
        </OpsPage>
    )
}
