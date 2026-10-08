import * as chart from '../../lib/chart'

import { ChartFrame } from './ChartFrame'

function Range({ bucket, index, count, axisMax }) {
    const x = chart.x(index, count)
    const width = chart.slot(count)
    const center = x + width / 2
    const p95Y = chart.y(bucket.p95, axisMax)
    const p50Y = chart.y(bucket.p50 ?? bucket.p95, axisMax)
    const maxY = chart.y(bucket.max ?? bucket.p95, axisMax)
    const failed = bucket.failed ?? 0

    return (
        <g>
            <title>
                {[bucket.label, `p50 ${chart.duration(bucket.p50)}`, `p95 ${chart.duration(bucket.p95)}`, `max ${chart.duration(bucket.max)}`, failed > 0 ? `${failed} failed` : null]
                    .filter(Boolean)
                    .join(' · ')}
            </title>
            <rect x={x + 2} y={maxY - 6} width={Math.max(2, width - 4)} height={Math.max(8, p50Y - maxY + 12)} fill="transparent" />
            <line x1={center} x2={center} y1={maxY} y2={p95Y} className="stroke-zinc-300" />
            <line x1={x + 3} x2={x + width - 3} y1={maxY} y2={maxY} className="stroke-zinc-800" strokeWidth="1.5" />
            <rect x={x + 2} y={p95Y} width={Math.max(2, width - 4)} height={Math.max(2, p50Y - p95Y)} rx="2" className="fill-ops-ink" />
        </g>
    )
}

export function RangeBars({ buckets, ceiling = null }) {
    const count = buckets.length
    const top = Math.max(ceiling ?? 0, ...buckets.map((bucket) => bucket.max ?? bucket.p95 ?? 0), 0)
    const axisMax = chart.niceSeconds(top * 1.08)
    const failY = chart.y(ceiling ?? top, axisMax)

    return (
        <ChartFrame axisMax={axisMax} formatTick={chart.duration} labels={buckets.map((bucket) => bucket.label)}>
            {ceiling !== null && (
                <>
                    <line
                        x1={chart.PAD_LEFT}
                        x2={chart.WIDTH - chart.PAD_RIGHT}
                        y1={chart.y(ceiling, axisMax)}
                        y2={chart.y(ceiling, axisMax)}
                        className="stroke-ops-warn"
                        strokeWidth="1.5"
                        strokeDasharray="5 4"
                    />
                    <text x={chart.WIDTH - chart.PAD_RIGHT} y={chart.y(ceiling, axisMax) - 4} textAnchor="end" className="fill-ops-warn!">
                        timeout {chart.duration(ceiling)}
                    </text>
                </>
            )}

            {buckets.map((bucket, index) => bucket.p95 !== null && bucket.p95 !== undefined && <Range key={index} bucket={bucket} index={index} count={count} axisMax={axisMax} />)}

            {buckets.map(
                (bucket, index) =>
                    (bucket.failed ?? 0) > 0 && (
                        <circle key={`fail-${index}`} cx={chart.x(index, count) + chart.slot(count) / 2} cy={failY} r="4.5" className="fill-ops-fail stroke-white" strokeWidth="1.5">
                            <title>{`${bucket.label} · ${bucket.failed} failed`}</title>
                        </circle>
                    ),
            )}
        </ChartFrame>
    )
}
