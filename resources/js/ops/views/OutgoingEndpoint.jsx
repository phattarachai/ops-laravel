import { Link } from '@inertiajs/react'
import { ChevronLeft } from 'lucide-react'

import { Kpi, KpiStrip } from '../charts/Kpi'
import { Legend } from '../charts/Legend'
import { OutcomeSplit } from '../charts/OutcomeSplit'
import { TimeFilter } from '../charts/TimeFilter'
import { Histogram } from '../charts/chart/Histogram'
import { HourOfDay } from '../charts/chart/HourOfDay'
import { RangeBars } from '../charts/chart/RangeBars'
import { StackedBars } from '../charts/chart/StackedBars'
import { t } from '../i18n'
import { duration } from '../lib/chart'
import { number } from '../lib/format'
import { OpsPage } from '../shell/OpsPage'
import { Badge } from '../ui/Badge'
import { Section } from '../ui/Section'
import { SectionGrid } from '../ui/SectionGrid'
import { StatusTable } from './outgoing/StatusTable'
import { BAR_SEGMENTS, barLegend, deviationLabel, outcomeLabels } from './outgoing/shared'

function Heading({ endpoint, backUrl }) {
    return (
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <Link href={backUrl} className="inline-flex items-center text-zinc-500 hover:text-zinc-800" aria-label={t('Back to Outgoing')} title={t('Back to Outgoing')}>
                <ChevronLeft className="size-4" />
            </Link>
            <h1 className="text-sm font-semibold break-all text-zinc-900">{endpoint.endpoint}</h1>
            <Badge tone={endpoint.health_tone}>{t(endpoint.health_label)}</Badge>
        </div>
    )
}

function Summary({ endpoint }) {
    return (
        <KpiStrip>
            <Kpi label={t('Calls')} value={number(endpoint.calls)} />
            <Kpi label={t('Failed')} value={number(endpoint.failed)} tone={endpoint.failed > 0 ? 'fail' : undefined} />
            <Kpi label={t('Error')} value={`${endpoint.error_pct}%`} tone={endpoint.health === 'incident' ? 'fail' : undefined} />
            <Kpi
                label={t('vs normal')}
                value={<span title={endpoint.baseline_error_pct === null ? undefined : t('7-day baseline :value%', { value: endpoint.baseline_error_pct })}>{deviationLabel(endpoint.deviation)}</span>}
                tone={endpoint.deviation > 3 ? 'fail' : undefined}
            />
            <Kpi label="p50" value={duration(endpoint.p50)} />
            <Kpi label="p95" value={duration(endpoint.p95)} />
            <Kpi label="max" value={duration(endpoint.max)} />
        </KpiStrip>
    )
}

function Charts({ buckets, outcomes, histogram, windowLabel }) {
    const callBars = buckets.map(({ failed, ...bucket }) => bucket)

    return (
        <>
            <div className="grid gap-3 xl:grid-cols-3">
                <Section title={t('Calls')} hint={windowLabel} className="xl:col-span-2" actions={<Legend items={barLegend()} />}>
                    <StackedBars buckets={callBars} segments={BAR_SEGMENTS} />
                </Section>
                <Section title={t('Outcomes')} hint={windowLabel}>
                    <OutcomeSplit outcomes={outcomes} labels={outcomeLabels()} />
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
                                { tone: 'fail', label: t('failed'), shape: 'dot' },
                            ]}
                        />
                    }
                >
                    <RangeBars buckets={buckets} />
                </Section>
                <Section title={t('Distribution')} hint={windowLabel} tooltip={t('Response time of timed calls')}>
                    <Histogram bands={histogram} unit={t('calls')} />
                </Section>
            </div>
        </>
    )
}

export default function OutgoingEndpoint({ endpoint, buckets, outcomes, histogram, statuses, hour_of_day, time_filter, back_url }) {
    return (
        <OpsPage title={endpoint.endpoint} toolbar={<TimeFilter />}>
            <div className="space-y-3">
                <Heading endpoint={endpoint} backUrl={back_url} />

                {endpoint.is_dark ? (
                    <div className="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">{t('No calls to this endpoint in :window', { window: time_filter.label })}</div>
                ) : (
                    <>
                        <Summary endpoint={endpoint} />
                        <Charts buckets={buckets} outcomes={outcomes} histogram={histogram} windowLabel={time_filter.label} />
                    </>
                )}

                <SectionGrid>
                    <Section title={t('Hour of day')} hint={t('7 d')} actions={<Legend items={[{ tone: 'muted', label: t('calls') }, { tone: 'fail', label: t('failed') }]} />}>
                        <HourOfDay hours={hour_of_day} />
                    </Section>
                    <Section title={t('Methods & statuses')} hint={time_filter.label} flush>
                        <StatusTable statuses={statuses} />
                    </Section>
                </SectionGrid>
            </div>
        </OpsPage>
    )
}
