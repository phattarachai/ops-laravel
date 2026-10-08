import * as chart from '../../lib/chart'

import { CapMark, ChartFrame } from './ChartFrame'

export const ORIGIN_SEGMENTS = [
    { key: 'web', label: 'web', className: 'fill-ops-web' },
    { key: 'schedule', label: 'schedule', className: 'fill-ops-schedule' },
    { key: 'other', label: 'other', className: 'fill-ops-other' },
]

export const STATUS_SEGMENTS = [
    { key: 'ok', label: '2xx/3xx', className: 'fill-ops-web' },
    { key: 's4xx', label: '4xx', className: 'fill-ops-other' },
    { key: 's429', label: '429', className: 'fill-ops-warn' },
    { key: 's5xx', label: '5xx', className: 'fill-ops-fail' },
]

function tooltip(bucket, segments) {
    const parts = segments.filter((segment) => (bucket[segment.key] ?? 0) > 0).map((segment) => `${segment.label} ${bucket[segment.key]}`)

    if ((bucket.released ?? 0) > 0) {
        parts.push(`${bucket.released} released`)
    }

    if ((bucket.failed ?? 0) > 0) {
        parts.push(`${bucket.failed} failed`)
    }

    return [bucket.label, ...parts].join(' · ')
}

function Bar({ bucket, index, count, total, axisMax, capped, segments }) {
    const x = chart.x(index, count) + 1
    const width = Math.max(2, chart.slot(count) - 2)
    const scale = (chart.baseline - chart.y(total, axisMax)) / total
    const failed = bucket.failed ?? 0
    const released = bucket.released ?? 0

    let cursor = chart.baseline
    const rects = segments
        .filter((segment) => (bucket[segment.key] ?? 0) > 0)
        .map((segment) => {
            const height = Math.max(1.5, bucket[segment.key] * scale)
            cursor -= height

            return <rect key={segment.key} x={x} y={cursor} width={width} height={Math.max(1, height - 1)} className={segment.className} />
        })

    const releasedY = cursor - 4
    if (released > 0) {
        cursor -= 4
    }

    return (
        <g>
            <title>{tooltip(bucket, segments)}</title>
            <rect x={x} y={chart.PAD_TOP} width={width} height={chart.plotHeight} fill="transparent" />
            {rects}
            {released > 0 && <rect x={x} y={releasedY} width={width} height="3" className="fill-ops-warn" />}
            {failed > 0 && <rect x={x} y={cursor - 5} width={width} height="4" rx="1" className="fill-ops-fail" />}
            {capped && <CapMark x={x} width={width} total={total} />}
        </g>
    )
}

export function StackedBars({ buckets, segments = ORIGIN_SEGMENTS }) {
    const totals = buckets.map((bucket) => segments.reduce((sum, segment) => sum + (bucket[segment.key] ?? 0), 0))
    const capAt = chart.cap(totals)
    const axisMax = chart.niceMax(capAt ?? Math.max(1, ...totals))

    return (
        <ChartFrame axisMax={axisMax} labels={buckets.map((bucket) => bucket.label)}>
            {buckets.map(
                (bucket, index) =>
                    totals[index] > 0 && (
                        <Bar
                            key={index}
                            bucket={bucket}
                            index={index}
                            count={buckets.length}
                            total={totals[index]}
                            axisMax={axisMax}
                            capped={capAt !== null && totals[index] > capAt}
                            segments={segments}
                        />
                    ),
            )}
        </ChartFrame>
    )
}
