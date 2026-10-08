import * as chart from '../../lib/chart'
import { cn } from '../../lib/cn'
import { number } from '../../lib/format'

import { ChartFrame } from './ChartFrame'

const pad = (hour) => String(hour).padStart(2, '0')

function Hour({ hour, row, axisMax }) {
    const x = chart.x(hour, 24) + 2
    const width = chart.slot(24) - 4
    const runsY = chart.y(row.runs, axisMax)
    const failHeight = Math.max(3, (row.failed / axisMax) * chart.plotHeight)
    const failRate = row.runs > 0 ? (row.failed / row.runs) * 100 : 0

    return (
        <g>
            <title>{`${pad(hour)}:00 · ${number(row.runs)} runs · ${row.failed} failed`}</title>
            <rect x={x} y={chart.PAD_TOP} width={width} height={chart.plotHeight} fill="transparent" />
            {row.runs > 0 && <rect x={x} y={runsY} width={width} height={Math.max(2, chart.baseline - runsY)} rx="2" className="fill-ops-muted" />}
            {row.failed > 0 && (
                <>
                    <rect x={x} y={chart.baseline - failHeight} width={width} height={failHeight} rx="1" className="fill-ops-fail" />
                    <text x={x + width / 2} y={runsY - 4} textAnchor="middle" className={cn(failRate >= 1 && 'fill-ops-fail!')}>
                        {chart.percent(row.failed, row.runs)}
                    </text>
                </>
            )}
            <text x={x + width / 2} y={chart.HEIGHT - 4} textAnchor="middle">
                {pad(hour)}
            </text>
        </g>
    )
}

export function HourOfDay({ hours }) {
    const axisMax = chart.niceMax(Math.max(1, ...hours.map((row) => row.runs)))

    return (
        <ChartFrame>
            {hours.map((row, hour) => (
                <Hour key={hour} hour={hour} row={row} axisMax={axisMax} />
            ))}
        </ChartFrame>
    )
}
