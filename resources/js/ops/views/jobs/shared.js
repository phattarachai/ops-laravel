import { router } from '@inertiajs/react'

import { t } from '../../i18n'

export function visit(params) {
    router.get(window.location.pathname, { ...Object.fromEntries(new URLSearchParams(window.location.search)), ...params }, { preserveScroll: true, preserveState: true })
}

export function timelineUrl(day) {
    return `${window.location.pathname}?view=timeline&day=${day}`
}

export function dayStatus() {
    return {
        ok: { label: t('No failures'), cell: 'bg-ops-ok', text: 'text-white' },
        failed: { label: t('Has failures'), cell: 'bg-ops-fail', text: 'text-white' },
        idle: { label: t('Did not run'), cell: 'bg-zinc-100', text: 'text-zinc-400' },
    }
}

export function statusFilters() {
    return [
        { value: 'all', label: t('All'), match: () => true },
        { value: 'problem', label: t('Has failures'), match: (job) => job.failed > 0 },
        { value: 'ok', label: t('No failures'), match: (job) => job.failed === 0 },
    ]
}

export function healthLabel(job) {
    return t(job.health_label)
}

const HEALTH_RANK = { incident: 0, degraded: 1, ok: 2 }

export function healthRank(job) {
    return HEALTH_RANK[job.health] ?? (job.failed > 0 ? 1 : 2)
}

export function groupJobs(rows, sortRows) {
    const groups = new Map()

    rows.forEach((row) => {
        groups.set(row.group, [...(groups.get(row.group) ?? []), row])
    })

    return [...groups.entries()]
        .map(([label, groupRows]) => ({
            key: label,
            label,
            rows: [...groupRows].sort(sortRows),
            rank: Math.min(...groupRows.map(healthRank)),
            runs: groupRows.reduce((total, row) => total + row.runs, 0),
            failed: groupRows.reduce((total, row) => total + row.failed, 0),
        }))
        .sort((a, b) => a.rank - b.rank || a.label.localeCompare(b.label))
}

export function queuesOf(job) {
    return job.queues ?? (job.queue ? [job.queue] : [])
}

export function matchesQueue(job, queue) {
    return queue === 'all' || queuesOf(job).includes(queue)
}

export function matchesSearch(job, term) {
    return term === '' || job.job_class.toLowerCase().includes(term) || job.group.toLowerCase().includes(term) || queuesOf(job).some((queue) => queue.toLowerCase().includes(term))
}

export function dayTitle(cell, date) {
    const parts = [`${date?.weekday ?? ''} ${date?.label ?? cell.date}`.trim(), dayStatus()[cell.status].label]

    if (cell.runs > 0) {
        parts.push(t(':count runs', { count: cell.runs.toLocaleString('en-US') }))
    }

    if (cell.failed > 0) {
        parts.push(t(':count failed', { count: cell.failed }))
    }

    if (cell.released > 0) {
        parts.push(t(':count released', { count: cell.released }))
    }

    if (cell.max !== null && cell.max !== undefined) {
        parts.push(t('longest :duration', { duration: `${cell.max}s` }))
    }

    return parts.join(' · ')
}

export function compact(value) {
    if (value >= 10000) {
        return `${Math.round(value / 1000)}k`
    }

    if (value >= 1000) {
        return `${(value / 1000).toFixed(1)}k`
    }

    return String(value)
}
