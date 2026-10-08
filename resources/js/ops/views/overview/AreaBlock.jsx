import { Link } from '@inertiajs/react'
import { ChevronRight } from 'lucide-react'

import { OUTCOME_SEGMENTS, SparkBars } from '../../charts/SparkBars'
import { t } from '../../i18n'
import { cn } from '../../lib/cn'
import { Badge } from '../../ui'

function chartSegments() {
    return {
        outcome: OUTCOME_SEGMENTS,
        status: [
            { key: 'ok', color: '#d6d3d1', label: '2xx' },
            { key: 's4xx', color: '#a8a29e', label: '4xx' },
            { key: 's429', color: '#d97706', label: '429' },
            { key: 's5xx', color: '#dc2626', label: '5xx' },
        ],
        runs: [
            { key: 'ok', color: '#d6d3d1', label: t('Succeeded') },
            { key: 'failed', color: '#dc2626', label: t('Failed') },
        ],
    }
}

const TONES = {
    fail: 'text-ops-fail',
    warn: 'text-ops-warn',
}

function Stat({ stat }) {
    return (
        <div className="flex h-5.5 items-center justify-between gap-2">
            <dt className="truncate text-zinc-500">{t(stat.label)}</dt>
            <dd className={cn('font-semibold whitespace-nowrap tabular-nums text-zinc-900', TONES[stat.tone])}>
                {stat.value}
                {stat.sub && <span className="ml-1 text-[0.6875rem] font-normal text-zinc-500">{stat.sub}</span>}
            </dd>
        </div>
    )
}

export function AreaBlock({ area }) {
    const segments = area.chart.segments ?? chartSegments()[area.chart.kind] ?? OUTCOME_SEGMENTS

    return (
        <Link href={area.url} className="group block min-w-0 rounded border border-zinc-200 bg-white transition hover:border-ops-accent">
            <div className="flex min-h-8 items-center gap-1.5 border-b border-zinc-100 px-2.5 py-1">
                <h2 className="min-w-0 flex-1 truncate text-xs font-semibold text-zinc-800 group-hover:text-ops-accent-600">{t(area.title)}</h2>
                <Badge tone={area.health.tone}>{t(area.health.label)}</Badge>
                <ChevronRight className="size-3.5 shrink-0 text-zinc-400" />
            </div>
            <dl className="px-2.5 py-1">
                {area.stats.map((stat) => (
                    <Stat key={stat.label} stat={stat} />
                ))}
            </dl>
            <div className="h-9 px-2.5 pb-2" title={area.chart.span}>
                {area.chart.series.length > 0 && <SparkBars series={area.chart.series} segments={segments} width={200} height={28} fluid />}
            </div>
        </Link>
    )
}
