import { Link } from '@inertiajs/react'
import { Fragment } from 'react'

import { t } from '../../i18n'
import { cn } from '../../lib/cn'
import { number } from '../../lib/format'
import { compact, dayStatus, dayTitle } from './shared'

function Cell({ cell, date, href }) {
    const status = dayStatus()[cell.status]

    return (
        <td className={cn('h-7 border-b border-zinc-100 px-px py-0.5', date.weekend && 'bg-zinc-50')}>
            <Link
                href={href}
                title={dayTitle(cell, date)}
                className={cn('flex h-5 items-center justify-center rounded-[3px] text-[0.625rem] font-semibold tabular-nums hover:ring-2 hover:ring-zinc-400', status.cell, status.text, cell.status === 'ok' && 'opacity-80')}
            >
                {cell.failed > 0 ? cell.failed : cell.runs > 0 ? compact(cell.runs) : ''}
            </Link>
        </td>
    )
}

function Row({ job, dates, timelineUrl, showClass }) {
    const failedDays = job.history.filter((cell) => cell.status === 'failed').length

    return (
        <tr className="hover:bg-zinc-50/60">
            <td className="sticky left-0 z-10 h-7 max-w-72 min-w-56 border-r border-b border-zinc-100 bg-white px-2">
                <Link href={job.url} className="block truncate leading-tight font-semibold text-zinc-900 hover:text-ops-accent-600" title={job.job_class}>
                    {job.job}
                </Link>
                {showClass && job.namespace && <span className="block truncate text-[0.6875rem] leading-tight text-zinc-400">{job.namespace}</span>}
            </td>
            {job.history.map((cell, index) => (
                <Cell key={cell.date} cell={cell} date={dates[index]} href={timelineUrl(cell.date)} />
            ))}
            <td className="h-7 border-b border-l border-zinc-100 px-2 text-right whitespace-nowrap tabular-nums">
                {failedDays > 0 ? <span className="font-semibold text-ops-fail">{t(':count days failed', { count: number(failedDays) })}</span> : <span className="text-zinc-300">—</span>}
            </td>
        </tr>
    )
}

export function JobMatrix({ groups, dates, timelineUrl, showClass }) {
    return (
        <div className="overflow-x-auto rounded border border-zinc-200 bg-white">
            <table className="w-full border-separate border-spacing-0 text-xs">
                <thead>
                    <tr className="text-[0.6875rem] text-zinc-500">
                        <th className="sticky top-0 left-0 z-20 border-r border-b border-zinc-200 bg-zinc-50 px-2 py-1 text-left font-semibold">Job</th>
                        {dates.map((date) => (
                            <th
                                key={date.date}
                                className={cn('sticky top-0 w-9 min-w-9 border-b border-zinc-200 bg-zinc-50 px-px py-1 text-center leading-tight font-normal', date.today && 'text-ops-accent-600', date.weekend && 'bg-zinc-100')}
                            >
                                <span className="block font-semibold">{date.weekday}</span>
                                <span className="block text-[0.625rem]">{date.label}</span>
                            </th>
                        ))}
                        <th className="sticky top-0 border-b border-l border-zinc-200 bg-zinc-50 px-2 py-1 text-right font-semibold">{t(':count days', { count: dates.length })}</th>
                    </tr>
                </thead>
                <tbody>
                    {groups.length === 0 && (
                        <tr>
                            <td colSpan={dates.length + 2} className="px-2 py-6 text-center text-zinc-400">
                                {t('No job matches the filter')}
                            </td>
                        </tr>
                    )}
                    {groups.map((group) => (
                        <Fragment key={group.key}>
                            <tr>
                                <td colSpan={dates.length + 2} className="sticky left-0 h-6 border-b border-zinc-200 bg-zinc-50 px-2 text-[0.6875rem] font-semibold text-zinc-600">
                                    {group.label}
                                    <span className="ml-1.5 font-normal text-zinc-400">{t(':count jobs', { count: group.rows.length })}</span>
                                </td>
                            </tr>
                            {group.rows.map((job) => (
                                <Row key={job.job_class} job={job} dates={dates} timelineUrl={timelineUrl} showClass={showClass} />
                            ))}
                        </Fragment>
                    ))}
                </tbody>
            </table>
        </div>
    )
}
