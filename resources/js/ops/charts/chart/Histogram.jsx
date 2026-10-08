import * as chart from '../../lib/chart'
import { cn } from '../../lib/cn'
import { number } from '../../lib/format'

import { ChartFrame } from './ChartFrame'

function Band({ band, index, count, axisMax, unit }) {
    const x = chart.x(index, count) + 6
    const width = Math.max(4, chart.slot(count) - 12)
    const y = chart.y(band.count, axisMax)
    const height = Math.max(2, chart.baseline - y)
    const failHeight = band.failed > 0 ? Math.max(3, (height * band.failed) / band.count) : 0

    return (
        <g>
            <title>{[band.label, `${number(band.count)} ${unit}`, band.failed > 0 ? `${band.failed} failed` : null].filter(Boolean).join(' · ')}</title>
            <rect x={x} y={y} width={width} height={height} rx="3" className="fill-ops-ink" />
            {failHeight > 0 && <rect x={x} y={y} width={width} height={failHeight} rx="3" className="fill-ops-fail" />}
            <text x={x + width / 2} y={y - 4} textAnchor="middle" className={cn(band.failed > 0 && 'fill-ops-fail!')}>
                {chart.count(band.count)}
            </text>
            <text x={x + width / 2} y={chart.HEIGHT - 4} textAnchor="middle">
                {band.label}
            </text>
        </g>
    )
}

export function Histogram({ bands, unit = 'runs' }) {
    const visible = bands.filter((band) => band.count > 0)
    const axisMax = chart.niceMax(Math.max(1, ...visible.map((band) => band.count)) * 1.15)

    return (
        <ChartFrame>
            {visible.map((band, index) => (
                <Band key={band.label} band={band} index={index} count={visible.length} axisMax={axisMax} unit={unit} />
            ))}
        </ChartFrame>
    )
}
