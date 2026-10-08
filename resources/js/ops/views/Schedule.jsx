import { Link } from '@inertiajs/react'
import { ArrowLeft } from 'lucide-react'

import { RangeBars } from '../charts/chart/RangeBars'
import { StackedBars } from '../charts/chart/StackedBars'
import { Incidents } from '../charts/Incidents'
import { Kpi, KpiStrip } from '../charts/Kpi'
import { Legend } from '../charts/Legend'
import { TimeFilter } from '../charts/TimeFilter'
import { t } from '../i18n'
import { number } from '../lib/format'
import { relative } from '../lib/time'
import { OpsPage } from '../shell/OpsPage'
import { Badge } from '../ui/Badge'
import { DataTable } from '../ui/DataTable'
import { Section } from '../ui/Section'
import { SectionGrid } from '../ui/SectionGrid'
import { cadenceLabel } from './schedules/cadence'
import { HistoryDays, HistoryLegend } from './schedules/HistoryStrip'

const RUN_SEGMENTS = [
    { key: 'schedule', label: 'ran', className: 'fill-ops-schedule' },
    { key: 'other', label: 'skipped', className: 'fill-ops-other' },
]

const RUN_TONES = { success: 'green', failed: 'red', skipped: 'zinc', running: 'amber' }

function runColumns() {
    return [
        { key: 'at', label: t('Started'), className: 'text-zinc-500' },
        { key: 'status', label: t('Status'), render: (run) => <Badge tone={RUN_TONES[run.status]}>{run.status}</Badge> },
        { key: 'duration', label: t('Lasted'), align: 'right' },
        {
            key: 'jobs',
            label: t('Jobs dispatched'),
            render: (run) =>
                run.jobs > 0 ? (
                    <span className="block leading-tight whitespace-normal">
                        <span className="block">
                            {t(':count jobs', { count: number(run.jobs) })}
                            {run.jobs_failed > 0 && <span className="font-semibold text-ops-fail"> · {t(':count failed', { count: run.jobs_failed })}</span>}
                        </span>
                        <span className="block text-[0.6875rem] text-zinc-400">{run.job_classes.join(', ')}</span>
                    </span>
                ) : (
                    <span className="text-zinc-400">—</span>
                ),
        },
        { key: 'detail', label: t('Detail'), truncate: true, className: 'w-full text-zinc-500' },
    ]
}

function Heading({ name, task, summary, indexUrl }) {
    return (
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <Link href={indexUrl} className="inline-flex items-center gap-1 text-zinc-500 hover:text-zinc-800" title={t('Back to schedules')}>
                <ArrowLeft className="size-3.5" />
                Schedules
            </Link>
            <span className="text-zinc-300">/</span>
            <h1 className="text-sm font-semibold text-zinc-900">{name}</h1>
            {summary && <Badge tone={summary.health_color}>{t(summary.health_label)}</Badge>}
            {summary && (
                <span className="rounded-sm bg-zinc-100 px-1.5 text-[0.6875rem] text-zinc-600" title={summary.expressions.join(' · ')}>
                    {cadenceLabel(summary.cadence)}
                </span>
            )}
            {summary?.next_due && (
                <span className="text-zinc-500">
                    {t('Next run :at', { at: summary.next_due })} · {relative(summary.next_due_at)}
                </span>
            )}
            {name !== task && <span className="basis-full text-[0.6875rem] text-zinc-400">{task}</span>}
        </div>
    )
}

function Summary({ summary, incidents }) {
    if (!summary) {
        return null
    }

    return (
        <KpiStrip>
            <Kpi label="Runs" value={number(summary.runs)} />
            <Kpi label="Failed" value={number(summary.failed)} tone={summary.failed > 0 ? 'fail' : undefined} />
            <Kpi label="Skipped" value={number(summary.skipped)} />
            <Kpi label="Missed · 24h" value={summary.missed ?? '—'} tone={summary.missed > 0 ? 'warn' : undefined} />
            <Kpi label="p95" value={summary.p95} />
            <Kpi label={t('Last run')} value={summary.last_run_at} />
            <Kpi label="Incidents · 7d" value={number(incidents.length)} tone={incidents.length > 0 ? 'fail' : undefined} />
        </KpiStrip>
    )
}

export default function Schedule({ name, task, summary, buckets, runs, incidents, history, dates, time_filter, index_url }) {
    return (
        <OpsPage title={name} toolbar={<TimeFilter />}>
            <div className="space-y-3">
                <Heading name={name} task={task} summary={summary} indexUrl={index_url} />
                <Summary summary={summary} incidents={incidents} />

                <Section title={t('Last :count days', { count: dates.length })} tooltip={t('Number = runs (red = failures)')} actions={<HistoryLegend />}>
                    <HistoryDays history={history} dates={dates} />
                </Section>

                <div className="grid gap-3 lg:grid-cols-2">
                    <Section
                        title="Runs"
                        hint={time_filter.label}
                        actions={
                            <Legend
                                items={[
                                    { tone: 'schedule', label: 'ran' },
                                    { tone: 'other', label: 'skipped' },
                                    { tone: 'fail', label: 'failed' },
                                ]}
                            />
                        }
                    >
                        <StackedBars buckets={buckets} segments={RUN_SEGMENTS} />
                    </Section>
                    <Section
                        title={t('Duration')}
                        hint={time_filter.label}
                        actions={
                            <Legend
                                items={[
                                    { tone: 'ink', label: 'p50 → p95' },
                                    { tone: 'ink', label: 'max', shape: 'line' },
                                    { tone: 'fail', label: 'failed', shape: 'dot' },
                                ]}
                            />
                        }
                    >
                        <RangeBars buckets={buckets} />
                    </Section>
                </div>

                <SectionGrid min="36rem">
                    <Section title={t('Latest runs')} tooltip={t('The last 30 runs, whatever the window')} flush>
                        <DataTable columns={runColumns()} rows={runs} rowKey={(run, index) => `${run.at_iso}-${index}`} empty={t('No runs yet')} flush />
                    </Section>
                    <Section title="Incidents" hint={t(':count days', { count: 7 })} tooltip={t('Failures of the last 7 days, folded into incidents')} flush>
                        <Incidents incidents={incidents} empty={t('No failures in 7 days')} showDomains={false} />
                    </Section>
                </SectionGrid>
            </div>
        </OpsPage>
    )
}
