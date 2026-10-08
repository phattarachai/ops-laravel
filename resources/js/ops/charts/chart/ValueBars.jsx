import * as chart from '../../lib/chart'

import { ChartFrame } from './ChartFrame'

export function ValueBars({ buckets, unit = '' }) {
    const count = buckets.length
    const width = Math.max(2, chart.slot(count) - 2)
    const axisMax = chart.niceMax(Math.max(1, ...buckets.map((bucket) => bucket.value)))

    return (
        <ChartFrame axisMax={axisMax} labels={buckets.map((bucket) => bucket.label)}>
            {buckets.map((bucket, index) => {
                if (bucket.value <= 0) {
                    return null
                }

                const x = chart.x(index, count) + 1
                const y = chart.y(bucket.value, axisMax)

                return (
                    <g key={index}>
                        <title>{`${bucket.label} · ${bucket.value} ${unit}`}</title>
                        <rect x={x} y={chart.PAD_TOP} width={width} height={chart.plotHeight} fill="transparent" />
                        <rect x={x} y={y} width={width} height={Math.max(1.5, chart.baseline - y)} rx="1.5" className="fill-ops-ink" />
                    </g>
                )
            })}
        </ChartFrame>
    )
}
