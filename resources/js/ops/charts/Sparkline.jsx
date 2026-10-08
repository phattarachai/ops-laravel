export function Sparkline({ points, color = '#57534e', width = 96, height = 26, ceiling = null, fill = false, dot = false }) {
    const values = points.filter((point) => point !== null && point !== undefined)

    if (values.length === 0) {
        return <span className="text-zinc-300">—</span>
    }

    const pad = 3
    const min = Math.min(...values)
    const max = ceiling !== null ? Math.max(...values, ceiling) : Math.max(...values)
    const range = max - min || 1
    const stepX = points.length > 1 ? (width - pad * 2) / (points.length - 1) : 0
    const toY = (value) => height - pad - ((value - min) / range) * (height - pad * 2)

    const coords = points.flatMap((point, index) => (point === null || point === undefined ? [] : [[pad + index * stepX, toY(point)]]))
    const path = coords.map(([x, y], index) => `${index === 0 ? 'M' : 'L'}${x.toFixed(1)} ${y.toFixed(1)}`).join(' ')
    const [lastX, lastY] = coords[coords.length - 1]
    const ceilingY = ceiling !== null ? toY(ceiling) : null

    return (
        <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`} className="inline-block align-middle">
            {ceilingY !== null && ceilingY >= 0 && ceilingY <= height && (
                <line x1="0" y1={ceilingY} x2={width} y2={ceilingY} stroke="#d97706" strokeWidth="1" strokeDasharray="3 3" opacity="0.5" />
            )}
            {fill && coords.length > 1 && <path d={`M${coords[0][0]} ${height} ${path} L${lastX} ${height} Z`} fill={color} opacity="0.08" />}
            <path d={path} fill="none" stroke={color} strokeWidth="1.5" strokeLinejoin="round" strokeLinecap="round" />
            {dot && <circle cx={lastX} cy={lastY} r="2.5" fill={color} />}
        </svg>
    )
}
