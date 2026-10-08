import { router } from '@inertiajs/react'

import { t } from '../../i18n'

export const STATUS_SPARK_SEGMENTS = [
    { key: 'ok', color: '#e7e5e4', label: '2xx/3xx' },
    { key: 's4xx', color: '#a8a29e', label: '4xx' },
    { key: 's429', color: '#d97706', label: '429' },
    { key: 's5xx', color: '#dc2626', label: '5xx' },
]

export const STATUS_LEGEND = [
    { tone: 'muted', label: '2xx/3xx' },
    { tone: 'other', label: '4xx' },
    { tone: 'warn', label: '429' },
    { tone: 'fail', label: '5xx' },
]

export const STATUS_CHART_LEGEND = [
    { tone: 'web', label: '2xx/3xx' },
    { tone: 'other', label: '4xx' },
    { tone: 'warn', label: '429' },
    { tone: 'fail', label: '5xx' },
]

export function statusTitle(key) {
    return {
        s4xx: t('Client errors, 429 excluded'),
        s429: t('Rate limited (throttle)'),
        s5xx: t('Server errors'),
    }[key]
}

export function countTone(key, value) {
    if (value <= 0) {
        return 'text-zinc-400'
    }

    return {
        s429: 'font-semibold text-ops-warn',
        s5xx: 'font-semibold text-ops-fail',
    }[key]
}

export function deltaLabel(requests, delta) {
    if (delta === null || delta === undefined) {
        return requests > 0 ? t('new') : '—'
    }

    return `${delta > 0 ? '+' : ''}${delta}%`
}

function query(params) {
    const merged = {
        ...Object.fromEntries(new URLSearchParams(window.location.search)),
        ...params,
    }

    return Object.fromEntries(Object.entries(merged).filter(([, value]) => value !== undefined && value !== null && value !== ''))
}

export function urlWith(params) {
    const search = new URLSearchParams(query(params)).toString()

    return search ? `${window.location.pathname}?${search}` : window.location.pathname
}

export function visit(params) {
    router.get(window.location.pathname, query(params), {
        preserveScroll: true,
        preserveState: true,
    })
}
