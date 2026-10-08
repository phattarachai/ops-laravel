import { useEffect, useRef, useState } from 'react'

import * as chart from '../../lib/chart'

function useTextScale() {
    const ref = useRef(null)
    const [scale, setScale] = useState(1)

    useEffect(() => {
        const observer = new ResizeObserver(([entry]) => setScale(Math.max(1, entry.contentRect.width / chart.WIDTH)))
        observer.observe(ref.current)

        return () => observer.disconnect()
    }, [])

    return [ref, scale]
}

export function ChartFrame({ axisMax, formatTick = chart.count, labels = [], children }) {
    const width = chart.slot(labels.length)
    const [ref, scale] = useTextScale()

    return (
        <svg ref={ref} viewBox={`0 0 ${chart.WIDTH} ${chart.HEIGHT}`} className="ops-chart block h-auto w-full" style={{ '--ops-chart-scale': scale }} role="img">
            {axisMax !== undefined &&
                chart.ticks(axisMax).map((tick) => (
                    <g key={tick}>
                        <line x1={chart.PAD_LEFT} x2={chart.WIDTH - chart.PAD_RIGHT} y1={chart.y(tick, axisMax)} y2={chart.y(tick, axisMax)} className="stroke-ops-grid" />
                        <text x={chart.PAD_LEFT - 5} y={chart.y(tick, axisMax) + 3} textAnchor="end">
                            {formatTick(tick)}
                        </text>
                    </g>
                ))}

            {children}

            {chart.labelIndexes(labels.length).map((index) => (
                <text key={index} x={chart.x(index, labels.length) + width / 2} y={chart.HEIGHT - 4} textAnchor="middle">
                    {labels[index]}
                </text>
            ))}
        </svg>
    )
}

export function CapMark({ x, width, total }) {
    return (
        <>
            <path d={`M${x} ${chart.PAD_TOP + 12} l${width / 2} -4 l${width / 2} 4`} className="stroke-white" strokeWidth="2.5" fill="none" />
            <text x={x + width / 2} y={chart.PAD_TOP - 3} textAnchor="middle" className="fill-zinc-600!">
                {chart.count(total)}
            </text>
        </>
    )
}
