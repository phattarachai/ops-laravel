import { t } from '../../i18n'

export const SPARK_SEGMENTS = [
    { key: 'ok', color: '#d6d3d1', label: 'ok' },
    { key: 'http_4xx', color: '#a8a29e', label: '4xx' },
    { key: 'connect_error', color: '#d97706', label: 'connect error' },
    { key: 'timeout', color: '#dc2626', label: 'timeout' },
    { key: 'http_5xx', color: '#dc2626', label: '5xx' },
]

export const BAR_SEGMENTS = [
    { key: 'ok', label: 'ok', className: 'fill-ops-web' },
    { key: 'http_4xx', label: '4xx', className: 'fill-ops-other' },
    { key: 'connect_error', label: 'connect error', className: 'fill-ops-warn' },
    { key: 'timeout', label: 'timeout', className: 'fill-ops-fail' },
    { key: 'http_5xx', label: '5xx', className: 'fill-ops-fail' },
]

export function outcomeLabels() {
    return {
        ok: t('OK'),
        http_4xx: '4xx',
        http_5xx: '5xx',
        timeout: t('Timeout'),
        connect_error: t('Connect error'),
    }
}

export function sparkLegend() {
    return [
        { tone: 'muted', label: t('OK') },
        { tone: 'other', label: '4xx' },
        { tone: 'warn', label: t('Connect error') },
        { tone: 'fail', label: t('5xx / timeout') },
    ]
}

export function barLegend() {
    return [
        { tone: 'web', label: t('OK') },
        { tone: 'other', label: '4xx' },
        { tone: 'warn', label: t('Connect error') },
        { tone: 'fail', label: t('5xx / timeout') },
    ]
}

export const isProblem = (endpoint) => endpoint.health === 'incident' || endpoint.health === 'degraded'

export function healthFilters() {
    return [
        { value: 'all', label: t('All'), match: () => true },
        { value: 'problem', label: t('Unhealthy'), match: isProblem },
        { value: 'known_broken', label: t('Known broken'), match: (endpoint) => endpoint.health === 'known_broken' },
        { value: 'ok', label: t('Healthy'), match: (endpoint) => endpoint.health === 'ok' },
        { value: 'unknown', label: t('No data'), match: (endpoint) => endpoint.health === 'unknown' },
    ]
}

export function deviationLabel(deviation) {
    if (deviation === null || deviation === undefined) {
        return '—'
    }

    return t(':value pts', { value: `${deviation > 0 ? '+' : ''}${deviation}` })
}
