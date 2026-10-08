import { Link } from '@inertiajs/react'
import { Fragment } from 'react'

import { t } from '../../i18n'
import { duration } from '../../lib/chart'
import { cn } from '../../lib/cn'
import { number } from '../../lib/format'
import { compact } from './shared'

const HOURS = Array.from({ length: 24 }, (_, hour) => hour)

const pad = (hour) => String(hour).padStart(2, '0')

function shade(runs, max) {
    return runs === 0 ? 0 : 0.15 + 0.85 * (Math.log(runs + 1) / Math.log(max + 1))
}

function hourTitle(hour, cell) {
    return [
        `${pad(hour)}:00–${pad(hour)}:59`,
        t(':count runs', { count: number(cell.runs) }),
        cell.failed > 0 ? t(':count failed', { count: cell.failed }) : null,
        cell.released > 0 ? t(':count released', { count: cell.released }) : null,
        cell.max !== null ? t('longest :duration', { duration: duration(cell.max) }) : null,
        cell.wait_p95 !== null ? t('queue wait p95 :duration', { duration: duration(cell.wait_p95) }) : null,
    ]
        .filter(Boolean)
        .join(' · ')
}

function HourCell({ hour, cell, max, future }) {
    if (future) {
        return <td className="h-7 border-b border-zinc-100 px-px" />
    }

    const failed = cell.failed > 0

    return (
        <td className="h-7 border-b border-zinc-100 px-px py-0.5" title={hourTitle(hour, cell)}>
            <span className="relative flex h-5 items-center justify-center overflow-hidden rounded-[3px] bg-zinc-50 text-[0.625rem] tabular-nums">
                <span className={cn('absolute inset-0', failed ? 'bg-ops-fail' : 'bg-ops-ink')} style={{ opacity: failed ? 1 : shade(cell.runs, max) }} />
                <span className={cn('relative font-semibold', failed || shade(cell.runs, max) > 0.55 ? 'text-white' : 'text-zinc-500')}>{failed ? cell.failed : cell.runs > 0 ? compact(cell.runs) : ''}</span>
            </span>
        </td>
    )
}

function LoadRows({ hours, isFuture }) {
    const max = Math.max(1, ...hours.map((cell) => cell.runs))

    return (
        <>
            <tr className="bg-zinc-50/60">
                <td className="sticky left-0 z-10 h-7 border-r border-b border-zinc-200 bg-zinc-50 px-2 font-semibold text-zinc-700">{t('All jobs')}</td>
                {HOURS.map((hour) => (
                    <HourCell key={hour} hour={hour} cell={hours[hour]} max={max} future={isFuture(hour)} />
                ))}
                <td className="h-7 border-b border-l border-zinc-200 px-2 text-right font-semibold tabular-nums">{number(hours.reduce((total, cell) => total + cell.runs, 0))}</td>
            </tr>
            <tr className="bg-zinc-50/60">
                <td className="sticky left-0 z-10 h-7 border-r border-b border-zinc-200 bg-zinc-50 px-2 text-zinc-500">{t('Queue wait p95')}</td>
                {HOURS.map((hour) => (
                    <td key={hour} className="h-7 border-b border-zinc-200 px-px text-center text-[0.625rem] text-zinc-500 tabular-nums">
                        {!isFuture(hour) && hours[hour].wait_p95 !== null ? duration(hours[hour].wait_p95) : ''}
                    </td>
                ))}
                <td className="h-7 border-b border-l border-zinc-200" />
            </tr>
        </>
    )
}

function JobRow({ job, max, isFuture, showClass }) {
    return (
        <tr className="hover:bg-zinc-50/60">
            <td className="sticky left-0 z-10 h-7 max-w-72 min-w-56 border-r border-b border-zinc-100 bg-white px-2">
                <Link href={job.url} className="block truncate leading-tight font-semibold text-zinc-900 hover:text-ops-accent-600" title={job.job_class}>
                    {job.job}
                </Link>
                {showClass && job.namespace && <span className="block truncate text-[0.6875rem] leading-tight text-zinc-400">{job.namespace}</span>}
            </td>
            {HOURS.map((hour) => (
                <HourCell key={hour} hour={hour} cell={job.hours[hour]} max={max} future={isFuture(hour)} />
            ))}
            <td className="h-7 border-b border-l border-zinc-100 px-2 text-right whitespace-nowrap tabular-nums">
                {number(job.runs)}
                {job.failed > 0 && <span className="font-semibold text-ops-fail"> · {number(job.failed)}</span>}
            </td>
        </tr>
    )
}

export function HourHeatmap({ timeline, groups, showClass, toolbar }) {
    const nowHour = timeline.now_hour
    const isFuture = (hour) => timeline.date === timeline.today && hour > nowHour
    const max = Math.max(1, ...groups.flatMap((group) => group.rows.flatMap((job) => job.hours.map((cell) => cell.runs))))

    return (
        <div className="space-y-2">
            {toolbar}
            <div className="overflow-x-auto rounded border border-zinc-200 bg-white">
                <table className="w-full border-separate border-spacing-0 text-xs">
                    <thead>
                        <tr className="text-[0.6875rem] text-zinc-500">
                            <th className="sticky top-0 left-0 z-20 border-r border-b border-zinc-200 bg-zinc-50 px-2 py-1 text-left font-semibold">Job</th>
                            {HOURS.map((hour) => (
                                <th
                                    key={hour}
                                    className={cn('sticky top-0 w-8 min-w-8 border-b border-zinc-200 bg-zinc-50 px-px py-1 text-center font-normal', hour === nowHour && timeline.date === timeline.today && 'font-semibold text-ops-accent-600')}
                                >
                                    {pad(hour)}
                                </th>
                            ))}
                            <th className="sticky top-0 border-b border-l border-zinc-200 bg-zinc-50 px-2 py-1 text-right font-semibold">Runs · fail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <LoadRows hours={timeline.hours} isFuture={isFuture} />
                        {groups.length === 0 && (
                            <tr>
                                <td colSpan={26} className="px-2 py-6 text-center text-zinc-400">
                                    {t('No job matching the filter ran on this day')}
                                </td>
                            </tr>
                        )}
                        {groups.map((group) => (
                            <Fragment key={group.key}>
                                <tr>
                                    <td colSpan={26} className="sticky left-0 h-6 border-b border-zinc-200 bg-zinc-50 px-2 text-[0.6875rem] font-semibold text-zinc-600">
                                        {group.label}
                                        <span className="ml-1.5 font-normal text-zinc-400">
                                            {t(':count jobs', { count: group.rows.length })} · {t(':count runs', { count: number(group.runs) })}
                                        </span>
                                    </td>
                                </tr>
                                {group.rows.map((job) => (
                                    <JobRow key={job.job_class} job={job} max={max} isFuture={isFuture} showClass={showClass} />
                                ))}
                            </Fragment>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    )
}

export function HeatmapLegend() {
    return (
        <span className="inline-flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.6875rem] text-zinc-500">
            <span className="inline-flex items-center gap-1.5">
                <span className="inline-block h-3 w-6 rounded-[2px] bg-linear-to-r from-ops-ink/15 to-ops-ink" />
                {t('Runs per hour')}
            </span>
            <span className="inline-flex items-center gap-1.5">
                <span className="inline-block h-3 w-2 rounded-[2px] bg-ops-fail" />
                {t('Has failures (number = count)')}
            </span>
        </span>
    )
}
