import { Link } from '@inertiajs/react'
import { Fragment } from 'react'

import { t } from '../../i18n'
import { cn } from '../../lib/cn'
import { number } from '../../lib/format'
import { cadenceLabel, cellStatus, cellTitle } from './cadence'

function Cell({ cell, date, href }) {
    const status = cellStatus()[cell.status]
    const label = cell.failed > 0 ? cell.failed : cell.status === 'missed' ? '!' : ''

    return (
        <td className={cn('h-7 border-b border-zinc-100 px-px py-0.5', date.weekend && 'bg-zinc-50')}>
            <Link
                href={href}
                title={cellTitle(cell, date)}
                className={cn('flex h-5 items-center justify-center rounded-[3px] text-[0.625rem] font-semibold tabular-nums hover:ring-2 hover:ring-zinc-400', status.cell, status.text)}
            >
                {label}
            </Link>
        </td>
    )
}

function Row({ task, dates, timelineUrl }) {
    const failedDays = task.history.filter((cell) => cell.status === 'failed').length
    const missedDays = task.history.filter((cell) => cell.status === 'missed').length

    return (
        <tr className="hover:bg-zinc-50/60">
            <td className="sticky left-0 z-10 h-7 max-w-72 min-w-56 border-r border-b border-zinc-100 bg-white px-2">
                <Link href={task.url} className="block truncate font-semibold text-zinc-900 hover:text-ops-accent-600" title={task.task}>
                    {task.name}
                </Link>
            </td>
            <td className="h-7 border-r border-b border-zinc-100 px-2 whitespace-nowrap text-zinc-500" title={task.expressions.join(' · ')}>
                {cadenceLabel(task.cadence)}
            </td>
            {task.history.map((cell, index) => (
                <Cell key={cell.date} cell={cell} date={dates[index]} href={timelineUrl(cell.date)} />
            ))}
            <td className="h-7 border-b border-l border-zinc-100 px-2 text-right whitespace-nowrap tabular-nums">
                {failedDays > 0 && <span className="font-semibold text-ops-fail">{t(':count days failed', { count: number(failedDays) })}</span>}
                {failedDays > 0 && missedDays > 0 && <span className="text-zinc-300"> · </span>}
                {missedDays > 0 && <span className="font-semibold text-ops-warn">{t(':count days missed', { count: number(missedDays) })}</span>}
                {failedDays === 0 && missedDays === 0 && <span className="text-zinc-300">—</span>}
            </td>
        </tr>
    )
}

export function ScheduleMatrix({ groups, dates, timelineUrl }) {
    return (
        <div className="overflow-x-auto rounded border border-zinc-200 bg-white">
            <table className="w-full border-separate border-spacing-0 text-xs">
                <thead>
                    <tr className="text-[0.6875rem] text-zinc-500">
                        <th className="sticky top-0 left-0 z-20 border-r border-b border-zinc-200 bg-zinc-50 px-2 py-1 text-left font-semibold">Task</th>
                        <th className="sticky top-0 border-r border-b border-zinc-200 bg-zinc-50 px-2 py-1 text-left font-semibold">{t('Cadence')}</th>
                        {dates.map((date) => (
                            <th
                                key={date.date}
                                className={cn('sticky top-0 w-8 min-w-8 border-b border-zinc-200 bg-zinc-50 px-px py-1 text-center leading-tight font-normal', date.today && 'text-ops-accent-600', date.weekend && 'bg-zinc-100')}
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
                            <td colSpan={dates.length + 3} className="px-2 py-6 text-center text-zinc-400">
                                {t('No task matches the filter')}
                            </td>
                        </tr>
                    )}
                    {groups.map((group) => (
                        <Fragment key={group.key}>
                            <tr>
                                <td colSpan={dates.length + 3} className="sticky left-0 h-6 border-b border-zinc-200 bg-zinc-50 px-2 text-[0.6875rem] font-semibold text-zinc-600">
                                    {group.label}
                                    <span className="ml-1.5 font-normal text-zinc-400">{group.rows.length}</span>
                                </td>
                            </tr>
                            {group.rows.map((task) => (
                                <Row key={task.task} task={task} dates={dates} timelineUrl={timelineUrl} />
                            ))}
                        </Fragment>
                    ))}
                </tbody>
            </table>
        </div>
    )
}
