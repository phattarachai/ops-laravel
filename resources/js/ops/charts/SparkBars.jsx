import { number } from '../lib/format'

export const OUTCOME_SEGMENTS = [
    { key: 'ok', color: '#d6d3d1', label: 'ok' },
    { key: 'timeout', color: '#dc2626', label: 'timeout' },
    { key: 'ssl', color: '#d97706', label: 'ssl' },
    { key: 'http_error', color: '#78716c', label: 'error' },
]

export function SparkBars({ series, segments = OUTCOME_SEGMENTS, width = 96, height = 22, fluid = false }) {
    if (series.length === 0) {
        return <span className="text-zinc-300">—</span>
    }

    const gap = Math.max(1, (width / series.length) * 0.15)
    const barWidth = (width - (series.length - 1) * gap) / series.length
    const totals = series.map((bucket) => segments.reduce((sum, segment) => sum + (bucket[segment.key] ?? 0), 0))
    const max = Math.max(1, ...totals)

    return (
        <svg
            width={fluid ? '100%' : width}
            height={height}
            viewBox={`0 0 ${width} ${height}`}
            preserveAspectRatio={fluid ? 'none' : undefined}
            className={fluid ? 'block' : 'inline-block align-middle'}
        >
            {series.map((bucket, index) => {
                if (totals[index] === 0) {
                    return null
                }

                const x = index * (barWidth + gap)
                const scale = (totals[index] / max) * (height - 1)
                let y = height

                return (
                    <g key={index}>
                        <title>{segments.filter((segment) => bucket[segment.key] > 0).map((segment) => `${segment.label} ${number(bucket[segment.key])}`).join(' · ')}</title>
                        {segments.map((segment) => {
                            if (!(bucket[segment.key] > 0)) {
                                return null
                            }

                            const segmentHeight = (bucket[segment.key] / totals[index]) * scale
                            y -= segmentHeight

                            return <rect key={segment.key} x={x} y={y} width={barWidth} height={segmentHeight} fill={segment.color} rx={fluid ? undefined : 0.5} />
                        })}
                    </g>
                )
            })}
        </svg>
    )
}
