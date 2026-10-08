import { t } from '../../i18n'

export const GROUP_ORDER = {
    list: ['frequent', 'hourly', 'daily', 'weekly', 'other'],
    timeline: ['daily', 'weekly', 'hourly', 'frequent', 'other'],
}

const DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

export function groupLabels() {
    return { frequent: t('Every few minutes'), hourly: t('Hourly'), daily: t('Daily'), weekly: t('Weekly'), other: t('Other') }
}

export function cellStatus() {
    return {
        ok: { label: t('Ran'), cell: 'bg-ops-ok', text: 'text-white' },
        failed: { label: t('Has failures'), cell: 'bg-ops-fail', text: 'text-white' },
        missed: { label: t('Missed'), cell: 'bg-ops-warn', text: 'text-white' },
        skipped: { label: t('Skipped all day'), cell: 'bg-ops-other', text: 'text-white' },
        idle: { label: t('Not due'), cell: 'bg-zinc-100', text: 'text-zinc-400' },
    }
}

export function clock(minuteOfDay) {
    const hours = Math.floor(minuteOfDay / 60)
    const minutes = Math.floor(minuteOfDay % 60)

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`
}

function dayLabel(days) {
    if (!days) {
        return ''
    }

    if (days.join(',') === '1,2,3,4,5') {
        return t('Mon–Fri')
    }

    if (days.join(',') === '0,6') {
        return t('Sat–Sun')
    }

    return days.map((day) => t(DAY_NAMES[day])).join(' ')
}

function partLabel(part) {
    const days = dayLabel(part.days)
    const suffix = days === '' ? '' : ` · ${days}`
    const times = part.slots.map(clock).join(', ')

    if (part.kind === 'frequent' && part.interval === 1) {
        return `${t('Every minute')}${suffix}`
    }

    if (part.kind === 'frequent') {
        const marks = part.marks ? ` (${part.marks.map((minute) => `:${String(minute).padStart(2, '0')}`).join(' ')})` : ''

        return `${t('Every :count min', { count: part.interval })}${marks}${suffix}`
    }

    if (part.kind === 'hourly') {
        return `${t('Hourly at :minute', { minute: `:${String(part.minute).padStart(2, '0')}` })}${suffix}`
    }

    if (part.kind === 'daily') {
        return t('Daily :times', { times })
    }

    if (part.kind === 'weekly') {
        return `${days} ${times}`
    }

    return part.expression
}

export function cadenceLabel(cadence) {
    return cadence.parts?.length ? cadence.parts.map(partLabel).join(' + ') : cadence.label
}

export function groupTasks(tasks, order) {
    const labels = groupLabels()

    return order
        .map((kind) => ({
            key: kind,
            label: labels[kind],
            rows: tasks.filter((task) => task.cadence.kind === kind).sort((a, b) => a.cadence.sort - b.cadence.sort || a.name.localeCompare(b.name)),
        }))
        .filter((group) => group.rows.length > 0)
}

export function cellTitle(cell, date) {
    const parts = [`${date?.weekday ?? ''} ${date?.label ?? cell.date}`.trim(), cellStatus()[cell.status].label]

    if (cell.runs > 0) {
        parts.push(t(':count runs', { count: cell.runs }))
    }

    if (cell.failed > 0) {
        parts.push(t(':count failed', { count: cell.failed }))
    }

    if (cell.skipped > 0) {
        parts.push(t(':count skipped', { count: cell.skipped }))
    }

    if (cell.max !== null && cell.max !== undefined) {
        parts.push(t('longest :duration', { duration: `${cell.max}s` }))
    }

    return parts.join(' · ')
}
