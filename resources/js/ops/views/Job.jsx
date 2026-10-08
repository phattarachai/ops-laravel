import { Link } from '@inertiajs/react'
import { ArrowLeft } from 'lucide-react'

import { Histogram } from '../charts/chart/Histogram'
import { HourOfDay } from '../charts/chart/HourOfDay'
import { RangeBars } from '../charts/chart/RangeBars'
import { ORIGIN_SEGMENTS, StackedBars } from '../charts/chart/StackedBars'
import { Incidents } from '../charts/Incidents'
import { Kpi, KpiStrip } from '../charts/Kpi'
import { Legend } from '../charts/Legend'
import { TimeFilter } from '../charts/TimeFilter'
import { t } from '../i18n'
import { cn } from '../lib/cn'
import { number } from '../lib/format'
import { relative } from '../lib/time'
import { OpsPage } from '../shell/OpsPage'
import { Badge } from '../ui/Badge'
import { DataTable } from '../ui/DataTable'
import { Section } from '../ui/Section'
import { SectionGrid } from '../ui/SectionGrid'
import { JobDays, JobStripLegend } from './jobs/JobStrip'

const RUN_TONES = { success: 'green', failed: 'red', timed_out: 'red', released: 'amber' }

function originLabel(key) {
    return { schedule: t('Scheduled run'), web: t('Web request'), other: t('Other (admin / console / unknown)') }[key] ?? key
}

function originNote(origin) {
    return [origin.released > 0 ? t(':count released (retried later)', { count: number(origin.released) }) : null, origin.wait_p95 ? t('wait p95 :duration', { duration: origin.wait_p95 }) : null].filter(Boolean).join(' · ')
}

function runColumns(runs) {
    const columns = [
        { key: 'at', label: t('Started'), className: 'text-zinc-500', sortable: true, sortDir: 'desc', sortValue: (run) => run.at_iso, render: (run) => <span title={relative(run.at_iso) ?? undefined}>{run.at}</span> },
        {
            key: 'status',
            label: t('Status'),
            sortable: true,
            render: (run) => (
                <span title={run.error ?? undefined} className={cn(run.error && 'cursor-help')}>
                    <Badge tone={RUN_TONES[run.status]}>{run.status}</Badge>
                </span>
            ),
        },
        { key: 'duration', label: t('Lasted'), align: 'right', sortable: true, sortDir: 'desc', sortValue: (run) => run.seconds },
        { key: 'wait', label: <span title={t('Time from dispatch until the run started')}>{t('Queue wait')}</span>, align: 'right', className: 'text-zinc-500', sortable: true, sortDir: 'desc', sortValue: (run) => run.wait_seconds },
        { key: 'attempts', label: t('Attempt'), align: 'right', sortable: true, sortDir: 'desc', render: (run) => <span className={cn(run.attempts > 1 ? 'text-ops-warn' : 'text-zinc-400')}>{run.attempts}</span> },
        { key: 'origin', label: t('Origin'), className: 'text-zinc-500', render: (run) => run.origin ?? '—' },
        { key: 'queue', label: 'Queue', className: 'text-zinc-500' },
    ]

    return runs.some((run) => run.domain) ? [...columns, { key: 'domain', label: 'Domain', sortable: true, className: 'text-zinc-500' }] : columns
}

function originColumns() {
    return [
        { key: 'key', label: t('Origin'), className: 'font-semibold text-zinc-800', render: (origin) => originLabel(origin.key) },
        { key: 'runs', label: 'Runs', align: 'right', sortable: true, sortDir: 'desc', render: (origin) => number(origin.runs) },
        {
            key: 'failed',
            label: 'Failed',
            align: 'right',
            sortable: true,
            sortDir: 'desc',
            render: (origin) => <span className={cn(origin.failed > 0 ? 'font-semibold text-ops-fail' : 'text-zinc-400')}>{origin.failed > 0 ? `${number(origin.failed)} (${origin.fail_pct}%)` : '0'}</span>,
        },
        { key: 'p50', label: 'p50', align: 'right' },
        { key: 'p95', label: 'p95', align: 'right' },
        { key: 'chips', label: 'Queue', className: 'text-zinc-500', render: (origin) => origin.chips.join(', ') },
        { key: 'note', label: t('Note'), className: 'w-full text-zinc-500', render: originNote },
    ]
}

function sweepColumns() {
    return [
        { key: 'title', label: t('Run'), render: (sweep) => `${sweep.day} ${sweep.title}` },
        { key: 'task', label: 'Schedule', className: 'text-zinc-500' },
        { key: 'runs', label: 'Runs', align: 'right', sortable: true, sortDir: 'desc', render: (sweep) => number(sweep.runs) },
        { key: 'failed', label: 'Failed', align: 'right', sortable: true, sortDir: 'desc', render: (sweep) => <span className={cn(sweep.failed > 0 ? 'font-semibold text-ops-fail' : 'text-zinc-400')}>{number(sweep.failed)}</span> },
        { key: 'minutes', label: t('Took'), align: 'right', sortable: true, sortDir: 'desc', render: (sweep) => t(':count min', { count: sweep.minutes }) },
        { key: 'p50', label: t('p50 per run'), align: 'right' },
        { key: 'span', label: t('Span'), className: 'w-full text-zinc-500' },
    ]
}

function Heading({ job, summary, queues, indexUrl }) {
    return (
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <Link href={indexUrl} className="inline-flex items-center gap-1 text-zinc-500 hover:text-zinc-800" title={t('Back to jobs')}>
                <ArrowLeft className="size-3.5" />
                Jobs
            </Link>
            <span className="text-zinc-300">/</span>
            <h1 className="text-sm font-semibold text-zinc-900" title={job.job_class}>
                {job.job}
            </h1>
            {summary && <Badge tone={summary.health_color}>{t(summary.health_label)}</Badge>}
            {job.silenced && <Badge>silenced</Badge>}
            <span className="rounded-sm bg-zinc-100 px-1.5 text-[0.6875rem] text-zinc-600">{job.group}</span>
            {queues.map((queue) => (
                <span key={queue} className="rounded-sm border border-zinc-200 px-1.5 text-[0.6875rem] text-zinc-500">
                    {queue}
                </span>
            ))}
            {summary && (
                <span className="text-zinc-500" title={summary.last_run_at}>
                    {t('Last run :when', { when: relative(summary.last_run_at_iso) })}
                </span>
            )}
            <span className="basis-full text-[0.6875rem] text-zinc-400">{job.job_class}</span>
        </div>
    )
}

function Summary({ summary }) {
    return (
        <KpiStrip>
            <Kpi label="Runs" value={number(summary.runs)} />
            <Kpi label="Failed" value={number(summary.failed)} tone={summary.failed > 0 ? 'fail' : undefined} />
            <Kpi label="Released" value={number(summary.released)} tone={summary.released > 0 ? 'warn' : undefined} />
            <Kpi label="avg" value={summary.avg_label} />
            <Kpi label="p95" value={summary.p95_label} tone={summary.health === 'incident' ? 'fail' : undefined} />
            <Kpi label={t('Queue wait p95')} value={summary.wait_p95_label} />
            <Kpi label={t('Last run')} value={summary.last_run_at} />
        </KpiStrip>
    )
}

function Charts({ buckets, ceiling, hours, histogram, windowLabel }) {
    return (
        <div className="grid gap-3 lg:grid-cols-2">
            <Section
                title="Runs"
                hint={windowLabel}
                actions={
                    <Legend
                        items={[
                            { tone: 'schedule', label: 'schedule' },
                            { tone: 'web', label: 'web' },
                            { tone: 'other', label: t('other') },
                            { tone: 'warn', label: 'released' },
                            { tone: 'fail', label: 'failed' },
                        ]}
                    />
                }
            >
                <StackedBars buckets={buckets} segments={ORIGIN_SEGMENTS} />
            </Section>
            <Section
                title={t('Duration')}
                hint={windowLabel}
                actions={
                    <Legend
                        items={[
                            { tone: 'ink', label: 'p50 → p95' },
                            { tone: 'ink', label: 'max', shape: 'line' },
                            { tone: 'warn', label: t('worker timeout'), shape: 'dash' },
                            { tone: 'fail', label: 'failed', shape: 'dot' },
                        ]}
                    />
                }
            >
                <RangeBars buckets={buckets} ceiling={ceiling} />
            </Section>
            <Section title={t('By hour of day')} hint={t(':count days', { count: 7 })} actions={<Legend items={[{ tone: 'muted', label: 'runs' }, { tone: 'fail', label: 'failed' }]} />}>
                <HourOfDay hours={hours} />
            </Section>
            <Section title={t('Run time distribution')} hint={windowLabel} actions={<Legend items={[{ tone: 'ink', label: 'runs' }, { tone: 'fail', label: 'failed' }]} />}>
                <Histogram bands={histogram} />
            </Section>
        </div>
    )
}

function Breakdown({ origins, sweeps, windowLabel }) {
    if (origins.length <= 1 && sweeps.length === 0) {
        return null
    }

    return (
        <SectionGrid>
            {origins.length > 1 && (
                <Section title={t('By origin')} hint={windowLabel} flush>
                    <DataTable columns={originColumns()} rows={origins} rowKey="key" flush />
                </Section>
            )}
            {sweeps.length > 0 && (
                <Section title={t('Scheduled runs that dispatched this job')} tooltip={t('The latest 6 runs in the window')} flush>
                    <DataTable columns={sweepColumns()} rows={sweeps} rowKey="id" flush />
                </Section>
            )}
        </SectionGrid>
    )
}

export default function Job({ job, summary, queues, origins, sweeps, buckets, ceiling, hours, histogram, incidents, runs, history, dates, time_filter, index_url }) {
    return (
        <OpsPage title={job.job} toolbar={<TimeFilter />}>
            <div className="space-y-3">
                <Heading job={job} summary={summary} queues={queues} indexUrl={index_url} />
                {summary ? (
                    <Summary summary={summary} />
                ) : (
                    <p className="rounded border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-amber-700">{t('This job did not run in :window', { window: time_filter.label })}</p>
                )}

                <Section title={t('Last :count days', { count: dates.length })} tooltip={t('Number = runs per day (red = failures)')} actions={<JobStripLegend />}>
                    <JobDays history={history} dates={dates} />
                </Section>

                <Breakdown origins={origins} sweeps={sweeps} windowLabel={time_filter.label} />

                {summary && <Charts buckets={buckets} ceiling={ceiling} hours={hours} histogram={histogram} windowLabel={time_filter.label} />}

                <div className="grid items-start gap-3 xl:grid-cols-3">
                    <Section title={t('Latest runs')} tooltip={t('The last 30 runs, whatever the window · hover a failed status for the error')} flush>
                        <DataTable columns={runColumns(runs)} rows={runs} rowKey={(run, index) => `${run.at_iso}-${index}`} empty={t('No runs yet')} flush />
                    </Section>
                    <Section title="Incidents" hint={t(':count days', { count: 7 })} tooltip={t('Failures of the last 7 days, folded into incidents')} flush className="xl:col-span-2">
                        <Incidents incidents={incidents} empty={t('No failures in 7 days')} showDomains={incidents.some((incident) => incident.domains.length > 0)} />
                    </Section>
                </div>
            </div>
        </OpsPage>
    )
}
