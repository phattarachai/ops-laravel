import { cn } from '../../lib/cn'
import { cellStatus, cellTitle } from './cadence'

export function HistoryStrip({ history, dates }) {
    const status = cellStatus()

    return (
        <span className="inline-flex items-center gap-px align-middle">
            {history.map((cell, index) => (
                <span key={cell.date} title={cellTitle(cell, dates[index])} className={cn('inline-block h-3 w-2 rounded-[2px]', status[cell.status].cell, dates[index]?.today && 'ring-1 ring-zinc-400 ring-offset-1')} />
            ))}
        </span>
    )
}

export function HistoryDays({ history, dates }) {
    const status = cellStatus()

    return (
        <div className="grid gap-px" style={{ gridTemplateColumns: `repeat(${history.length}, minmax(0, 1fr))` }}>
            {history.map((cell, index) => (
                <div key={cell.date} className="min-w-0 text-center">
                    <span title={cellTitle(cell, dates[index])} className={cn('flex h-6 items-center justify-center rounded-[3px] text-[0.625rem] font-semibold tabular-nums', status[cell.status].cell, status[cell.status].text)}>
                        {cell.failed > 0 ? cell.failed : cell.runs > 0 ? cell.runs : ''}
                    </span>
                    <span className={cn('block truncate text-[0.625rem] leading-tight', dates[index].today ? 'font-semibold text-ops-accent-600' : 'text-zinc-400')}>
                        {dates[index].weekday} {dates[index].label}
                    </span>
                </div>
            ))}
        </div>
    )
}

export function HistoryLegend() {
    return (
        <span className="inline-flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.6875rem] text-zinc-500">
            {Object.entries(cellStatus()).map(([key, status]) => (
                <span key={key} className="inline-flex items-center gap-1.5">
                    <span className={cn('inline-block h-3 w-2 rounded-[2px]', status.cell)} />
                    {status.label}
                </span>
            ))}
        </span>
    )
}
