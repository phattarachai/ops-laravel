// see .ai/documents/infra/ops/ui.md — "Geometry" (twin of App\Support\Ops\Chart)

export const WIDTH = 600
export const HEIGHT = 150
export const PAD_LEFT = 36
export const PAD_RIGHT = 6
export const PAD_TOP = 14
export const PAD_BOTTOM = 18

export const plotWidth = WIDTH - PAD_LEFT - PAD_RIGHT
export const plotHeight = HEIGHT - PAD_TOP - PAD_BOTTOM
export const baseline = PAD_TOP + plotHeight

export function slot(count) {
    return plotWidth / Math.max(1, count)
}

export function x(index, count) {
    return PAD_LEFT + index * slot(count)
}

export function y(value, axisMax) {
    return PAD_TOP + plotHeight - (Math.min(value, axisMax) / Math.max(axisMax, 0.000001)) * plotHeight
}

export function cap(totals) {
    const positive = totals.filter((total) => total > 0).sort((a, b) => a - b)

    if (positive.length < 4) {
        return null
    }

    const capped = Math.max(10, Math.ceil(positive[Math.floor(0.9 * (positive.length - 1))] * 1.5))

    return positive[positive.length - 1] > capped * 1.2 ? capped : null
}

export function niceMax(max) {
    if (max <= 0) {
        return 1
    }

    const magnitude = 10 ** Math.floor(Math.log10(max))
    const factor = [1, 2, 2.5, 5, 10].find((step) => step * magnitude >= max)

    return factor * magnitude
}

export function niceSeconds(max) {
    return [1, 2, 5, 10, 20, 30, 60, 120, 180, 240, 300, 600, 900, 1200, 1800, 3600, 7200].find((step) => step >= max) ?? niceMax(max)
}

export function ticks(axisMax) {
    return [0, axisMax / 2, axisMax]
}

export function labelIndexes(count, labels = 6) {
    const every = Math.max(1, Math.ceil(count / labels))

    return Array.from({ length: count }, (_, index) => index).filter((index) => index % every === 0)
}

function round(value, precision = 0) {
    const factor = 10 ** precision

    return Math.round(value * factor) / factor
}

export function duration(seconds) {
    if (seconds === null || seconds === undefined) {
        return '—'
    }

    if (seconds <= 0) {
        return '0'
    }

    if (seconds < 1) {
        return `${Math.round(seconds * 1000)}ms`
    }

    if (seconds < 60) {
        return `${round(seconds, seconds < 10 ? 1 : 0)}s`
    }

    if (seconds < 3600) {
        return `${round(seconds / 60, 1)}m`
    }

    return `${round(seconds / 3600, 1)}h`
}

export function percent(part, whole) {
    const value = whole > 0 ? (part / whole) * 100 : 0

    return part > 0 && value < 0.1 ? '<0.1%' : `${round(value, 1)}%`
}

export function count(value) {
    if (value >= 1000) {
        return `${round(value / 1000, 1)}k`
    }

    return String(value < 10 ? round(value, 1) : Math.round(value))
}
