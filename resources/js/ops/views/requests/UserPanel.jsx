import { ExternalLink, X } from 'lucide-react'

import { ValueBars } from '../../charts/chart/ValueBars'
import { t } from '../../i18n'
import { cn } from '../../lib/cn'
import { number } from '../../lib/format'
import { Badge } from '../../ui/Badge'
import { DataTable } from '../../ui/DataTable'
import { Section } from '../../ui/Section'
import { RouteName } from './RouteName'
import { countTone, visit } from './shared'

function routeColumns() {
    return [
        {
            key: 'route',
            label: t('Route'),
            className: 'w-full max-w-0 min-w-48 overflow-hidden',
            render: (row) => (
                <span className="block truncate" title={row.route}>
                    <RouteName route={row.route} uri={row.uri} />
                </span>
            ),
        },
        { key: 'requests', label: t('Requests'), align: 'right', render: (row) => number(row.requests) },
        { key: 's429', label: '429', align: 'right', render: (row) => <span className={countTone('s429', row.s429)}>{number(row.s429)}</span> },
        { key: 's5xx', label: '5xx', align: 'right', render: (row) => <span className={countTone('s5xx', row.s5xx)}>{number(row.s5xx)}</span> },
        { key: 'peak_per_minute', label: t('Peak/min'), align: 'right', render: (row) => number(row.peak_per_minute) },
    ]
}

function Title({ user }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            {user.name}
            {user.segment && <Badge>{user.segment}</Badge>}
            {user.detail && <span className="font-normal text-zinc-400">{user.detail}</span>}
            {user.user_url && (
                <a href={user.user_url} title={t('Open user')} className="text-zinc-400 hover:text-zinc-700">
                    <ExternalLink className="size-3" />
                </a>
            )}
        </span>
    )
}

export function UserPanel({ user, windowLabel, threshold }) {
    if (!user) {
        return null
    }

    const flooding = threshold > 0 && user.peak_per_minute >= threshold

    return (
        <Section
            title={<Title user={user} />}
            hint={windowLabel}
            tooltip={user.scope_uri ?? user.scope ?? t('All routes')}
            actions={
                <>
                    <span className={cn('text-[0.6875rem]', flooding ? 'font-semibold text-ops-warn' : 'text-zinc-500')}>{t('Peak :count per minute', { count: number(user.peak_per_minute) })}</span>
                    <button type="button" onClick={() => visit({ user: undefined })} title={t('Close')} className="rounded p-0.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800">
                        <X className="size-3.5" />
                    </button>
                </>
            }
        >
            <div className="grid gap-3 xl:grid-cols-2">
                <div>
                    <p className="mb-1 text-[0.6875rem] font-semibold text-zinc-500">{t('Requests per hour')}</p>
                    <ValueBars buckets={user.hours} unit="req" />
                </div>
                <DataTable columns={routeColumns()} rows={user.routes} rowKey="route" rowHref={(row) => row.url} empty={t('No requests in this window')} />
            </div>
        </Section>
    )
}
