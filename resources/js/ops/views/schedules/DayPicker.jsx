import { ChevronLeft, ChevronRight } from 'lucide-react'

import { t } from '../../i18n'
import { cn } from '../../lib/cn'

function shift(date, days) {
    const [year, month, day] = date.split('-').map(Number)
    const next = new Date(year, month - 1, day + days)

    return [next.getFullYear(), String(next.getMonth() + 1).padStart(2, '0'), String(next.getDate()).padStart(2, '0')].join('-')
}

const STEP = 'inline-flex size-6 items-center justify-center rounded border border-zinc-300 bg-white text-zinc-600 hover:bg-zinc-100 disabled:pointer-events-none disabled:opacity-40'

export function DayPicker({ date, today, label, onChange }) {
    const isToday = date === today

    return (
        <div className="flex items-center gap-1">
            <button type="button" onClick={() => onChange(shift(date, -1))} className={STEP} title={t('Previous day')} aria-label={t('Previous day')}>
                <ChevronLeft className="size-3.5" />
            </button>
            <input
                type="date"
                value={date}
                max={today}
                onChange={(event) => event.target.value && onChange(event.target.value)}
                className="h-6 rounded border border-zinc-300 bg-white px-1.5 text-[0.6875rem] text-zinc-700"
                aria-label={t('Pick a date')}
            />
            <button type="button" onClick={() => onChange(shift(date, 1))} disabled={isToday} className={STEP} title={t('Next day')} aria-label={t('Next day')}>
                <ChevronRight className="size-3.5" />
            </button>
            <button
                type="button"
                onClick={() => onChange(today)}
                disabled={isToday}
                className={cn('h-6 rounded border px-2 text-[0.6875rem]', isToday ? 'border-zinc-800 bg-zinc-800 text-white' : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-100')}
            >
                {t('Today')}
            </button>
            <span className="ml-1 text-xs font-semibold text-zinc-800">{label}</span>
        </div>
    )
}
