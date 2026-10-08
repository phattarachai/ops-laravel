import { CircleAlert, CircleCheck, Mail, TrendingUp, X } from 'lucide-react'

import { t } from '../../i18n'
import { cn } from '../../lib/cn'
import { Badge } from '../../ui/Badge'

function kindLabel(kind) {
    return {
        opened: t('Started'),
        escalated: t('Escalated'),
        reminder: t('Still happening'),
        resolved: t('Resolved'),
        test: t('Test'),
    }[kind] ?? kind
}

function describe(event) {
    switch (event.type) {
        case 'opened':
            return { icon: CircleAlert, tone: 'text-ops-fail', title: t('Incident opened'), detail: event.value }
        case 'breach':
            return { icon: TrendingUp, tone: 'text-ops-warn', title: t('Last breach'), detail: event.value }
        case 'resolved':
            return { icon: CircleCheck, tone: 'text-ops-ok', title: t('Incident resolved') }
        default:
            return {
                icon: event.delivered ? Mail : X,
                tone: event.delivered ? 'text-zinc-500' : 'text-ops-fail',
                title: t(event.delivered ? ':kind notice sent via :channel' : ':kind notice via :channel failed', { kind: kindLabel(event.kind), channel: event.channel }),
                detail: event.error,
            }
    }
}

export function Timeline({ events }) {
    return (
        <ol className="space-y-2">
            {events.map((event, index) => {
                const { icon: Icon, tone, title, detail } = describe(event)

                return (
                    <li key={index} className="flex items-start gap-2">
                        <Icon className={cn('mt-0.5 size-3.5 shrink-0', tone)} />
                        <div className="min-w-0 flex-1">
                            <p className="text-zinc-800">
                                {title}
                                {event.type === 'notification' && !event.delivered && (
                                    <Badge tone="red" className="ml-1.5">
                                        {t('Failed')}
                                    </Badge>
                                )}
                            </p>
                            {detail && <p className={cn('text-[0.6875rem] break-words', event.type === 'notification' ? 'text-red-700' : 'text-zinc-500')}>{detail}</p>}
                        </div>
                        <time className="shrink-0 text-[0.6875rem] whitespace-nowrap text-zinc-400 tabular-nums" dateTime={event.at_iso}>
                            {event.at}
                        </time>
                    </li>
                )
            })}
        </ol>
    )
}
