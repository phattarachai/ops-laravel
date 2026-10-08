import { Link } from '@inertiajs/react'
import { Fragment, useState } from 'react'

import { t } from '../../i18n'
import { cn } from '../../lib/cn'
import { Pills } from '../../ui/Pills'
import { cadenceLabel, clock } from './cadence'

function ranges() {
    return [
        { value: '0-24', label: t('All day'), from: 0, to: 24 },
        { value: '0-9', label: '00–09', from: 0, to: 9 },
        { value: '6-18', label: '06–18', from: 6, to: 18 },
        { value: '18-24', label: '18–24', from: 18, to: 24 },
    ]
}

const RUN_TONES = { success: 'bg-ops-schedule', failed: 'bg-ops-fail', skipped: 'bg-ops-other', running: 'bg-ops-warn' }

function scaleFor(range) {
    const from = range.from * 60
    const span = (range.to - range.from) * 60

    return {
        visible: (minute) => minute >= from && minute <= from + span,
        left: (minute) => `${((minute - from) / span) * 100}%`,
        width: (minutes) => `${(minutes / span) * 100}%`,
        hours: Array.from({ length: range.to - range.from }, (_, index) => range.from + index),
    }
}

function Gridlines({ scale }) {
    return scale.hours.map((hour) => <span key={hour} className={cn('absolute inset-y-0 w-px', hour % 6 === 0 ? 'bg-zinc-200' : 'bg-zinc-100')} style={{ left: scale.left(hour * 60) }} />)
}

function NowLine({ scale, now }) {
    if (now === null || !scale.visible(now)) {
        return null
    }

    return <span className="absolute inset-y-0 z-10 w-px bg-ops-accent" style={{ left: scale.left(now) }} />
}

function hourTitle(hour, cell, perHour) {
    return [
        `${String(hour).padStart(2, '0')}:00 · ${cell.runs}/${perHour} runs`,
        cell.failed > 0 ? t(':count failed', { count: cell.failed }) : null,
        cell.skipped > 0 ? t(':count skipped', { count: cell.skipped }) : null,
    ]
        .filter(Boolean)
        .join(' · ')
}

function HourCells({ task, scale, now }) {
    const perHour = 60 / (task.cadence.interval || 60)

    return scale.hours.map((hour) => {
        const cell = task.hours[hour]
        const future = now !== null && hour * 60 > now
        const ratio = Math.min(1, cell.runs / perHour)

        return (
            <span
                key={hour}
                title={hourTitle(hour, cell, perHour)}
                className={cn('absolute inset-y-1.5 rounded-[2px]', future ? 'bg-transparent' : cell.failed > 0 ? 'bg-ops-fail' : cell.runs === 0 ? 'bg-ops-warn/40' : 'bg-ops-schedule')}
                style={{ left: `calc(${scale.left(hour * 60)} + 1px)`, width: `calc(${scale.width(60)} - 2px)`, opacity: future || cell.failed > 0 || cell.runs === 0 ? 1 : 0.35 + ratio * 0.65 }}
            />
        )
    })
}

function RunMarks({ task, scale }) {
    return (
        <>
            {task.planned.filter(scale.visible).map((minute) => (
                <span key={`p-${minute}`} title={t('Due :time', { time: clock(minute) })} className="absolute inset-y-1 w-px bg-zinc-400" style={{ left: scale.left(minute) }} />
            ))}
            {task.missed.filter(scale.visible).map((minute) => (
                <span
                    key={`m-${minute}`}
                    title={`${t('Missed')} · ${t('Due :time', { time: clock(minute) })}`}
                    className="absolute top-1/2 z-10 size-2.5 -translate-x-1/2 -translate-y-1/2 rotate-45 border-2 border-ops-warn bg-white"
                    style={{ left: scale.left(minute) }}
                />
            ))}
            {task.runs
                .filter((run) => scale.visible(run.minute))
                .map((run, index) => (
                    <span
                        key={`r-${index}`}
                        title={`${run.at} · ${run.status} · ${run.duration}`}
                        className={cn('absolute inset-y-1.5 z-10 min-w-[3px] rounded-[2px]', RUN_TONES[run.status] ?? 'bg-zinc-400')}
                        style={{ left: scale.left(run.minute), width: scale.width((run.seconds ?? 0) / 60) }}
                    />
                ))}
        </>
    )
}

function Summary({ task }) {
    if (task.hours) {
        const runs = task.hours.reduce((sum, cell) => sum + cell.runs, 0)
        const failed = task.hours.reduce((sum, cell) => sum + cell.failed, 0)

        return (
            <span className="tabular-nums">
                {runs.toLocaleString('en-US')}
                {failed > 0 && <span className="text-ops-fail"> · {failed}✕</span>}
            </span>
        )
    }

    const ran = task.runs.filter((run) => run.status !== 'skipped').length
    const failed = task.runs.filter((run) => run.status === 'failed').length

    return (
        <span className={cn('tabular-nums', task.missed.length > 0 && 'font-semibold text-ops-warn')}>
            {ran}/{task.planned.length || '—'}
            {failed > 0 && <span className="font-semibold text-ops-fail"> · {failed}✕</span>}
        </span>
    )
}

function Row({ task, scale, now }) {
    return (
        <div className="flex h-8 border-b border-zinc-100 hover:bg-zinc-50">
            <div className="flex w-64 shrink-0 items-center gap-2 border-r border-zinc-200 px-2">
                <div className="min-w-0 flex-1 leading-tight">
                    <Link href={task.url} className="block truncate font-semibold text-zinc-900 hover:text-ops-accent-600" title={task.task}>
                        {task.name}
                    </Link>
                    <span className="block truncate text-[0.625rem] text-zinc-400">{cadenceLabel(task.cadence)}</span>
                </div>
                <span className="shrink-0 text-[0.6875rem] text-zinc-500">
                    <Summary task={task} />
                </span>
            </div>
            <div className="relative flex-1">
                <Gridlines scale={scale} />
                {task.hours ? <HourCells task={task} scale={scale} now={now} /> : <RunMarks task={task} scale={scale} />}
                <NowLine scale={scale} now={now} />
            </div>
        </div>
    )
}

export function DayTimeline({ groups, isToday, toolbar }) {
    const [rangeKey, setRangeKey] = useState('0-24')
    const options = ranges()
    const scale = scaleFor(options.find((option) => option.value === rangeKey))
    const current = new Date()
    const now = isToday ? current.getHours() * 60 + current.getMinutes() : null

    return (
        <div className="rounded border border-zinc-200 bg-white">
            <div className="flex flex-wrap items-center gap-2 border-b border-zinc-100 px-2 py-1">
                {toolbar}
                <Pills options={options} value={rangeKey} onChange={setRangeKey} className="ml-auto" />
            </div>
            <div className="overflow-x-auto">
                <div className="min-w-[900px]">
                    <div className="sticky top-0 z-20 flex h-6 border-b border-zinc-200 bg-zinc-50 text-[0.625rem] text-zinc-500">
                        <div className="w-64 shrink-0 border-r border-zinc-200 px-2 leading-6 font-semibold">{t('Task · ran/due')}</div>
                        <div className="relative flex-1">
                            {scale.hours.map((hour) => (
                                <span key={hour} className="absolute top-0 pl-0.5 leading-6 tabular-nums" style={{ left: scale.left(hour * 60) }}>
                                    {String(hour).padStart(2, '0')}
                                </span>
                            ))}
                        </div>
                    </div>
                    {groups.length === 0 && <p className="px-2 py-6 text-center text-zinc-400">{t('No task matches the filter')}</p>}
                    {groups.map((group) => (
                        <Fragment key={group.key}>
                            <div className="h-6 border-b border-zinc-200 bg-zinc-50 px-2 text-[0.6875rem] leading-6 font-semibold text-zinc-600">
                                {group.label}
                                <span className="ml-1.5 font-normal text-zinc-400">{group.rows.length}</span>
                            </div>
                            {group.rows.map((task) => (
                                <Row key={task.task} task={task} scale={scale} now={now} />
                            ))}
                        </Fragment>
                    ))}
                </div>
            </div>
        </div>
    )
}

export function TimelineLegend() {
    const item = 'inline-flex items-center gap-1.5'

    return (
        <span className="inline-flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.6875rem] text-zinc-500">
            <span className={item}>
                <span className="inline-block h-3 w-px bg-zinc-400" />
                {t('Due time')}
            </span>
            <span className={item}>
                <span className="inline-block h-2.5 w-3 rounded-[2px] bg-ops-schedule" />
                {t('Run (width = duration)')}
            </span>
            <span className={item}>
                <span className="inline-block h-2.5 w-3 rounded-[2px] bg-ops-fail" />
                fail
            </span>
            <span className={item}>
                <span className="inline-block size-2 rotate-45 border-2 border-ops-warn bg-white" />
                {t('Missed')}
            </span>
            <span className={item}>
                <span className="inline-block h-3 w-px bg-ops-accent" />
                {t('Now')}
            </span>
        </span>
    )
}
