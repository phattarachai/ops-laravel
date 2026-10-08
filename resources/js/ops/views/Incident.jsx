import { Link, router } from '@inertiajs/react'
import { ChevronLeft } from 'lucide-react'
import { useState } from 'react'

import { Kpi, KpiStrip } from '../charts/Kpi'
import { t } from '../i18n'
import { relative } from '../lib/time'
import { number } from '../lib/format'
import { OpsPage } from '../shell/OpsPage'
import { Button } from '../ui/Button'
import { Section } from '../ui/Section'
import { SectionGrid, SectionStack } from '../ui/SectionGrid'
import { SeverityBadge, StatusBadge } from './incidents/shared'
import { Timeline } from './incidents/Timeline'
import { ruleSummary, typeLabel } from './rules/summary'

function Heading({ incident, backUrl, resolveUrl }) {
    const [resolving, setResolving] = useState(false)

    function resolve() {
        if (!window.confirm(t('Resolve this incident now?'))) {
            return
        }

        router.post(resolveUrl, {}, { preserveScroll: true, onStart: () => setResolving(true), onFinish: () => setResolving(false) })
    }

    return (
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <Link href={backUrl} className="inline-flex items-center text-zinc-500 hover:text-zinc-800" aria-label={t('Back to Incidents')} title={t('Back to Incidents')}>
                <ChevronLeft className="size-4" />
            </Link>
            <h1 className="text-sm font-semibold text-zinc-900">{incident.rule_name ?? t('Deleted rule')}</h1>
            <StatusBadge open={incident.is_open} />
            <SeverityBadge severity={incident.severity} />
            <span className="break-all text-zinc-500">{incident.key === '' ? '—' : incident.key}</span>
            {incident.is_open && (
                <Button size="sm" variant="outline" className="ml-auto" onClick={resolve} disabled={resolving}>
                    {t('Resolve now')}
                </Button>
            )}
        </div>
    )
}

function Summary({ incident }) {
    return (
        <KpiStrip>
            <Kpi label={t('Opened')} value={incident.opened} hint={relative(incident.opened_iso)} />
            <Kpi label={incident.is_open ? t('Open for') : t('Lasted')} value={incident.duration} />
            <Kpi label={t('Peak')} value={incident.peak} tone="fail" />
            <Kpi label={t('Last value')} value={incident.last} />
            <Kpi label={t('Last breach')} value={incident.last_breach} hint={relative(incident.last_breach_iso)} />
            <Kpi label={t('Notifications')} value={number(incident.notifications)} />
            {incident.resolved && <Kpi label={t('Resolved')} value={incident.resolved} tone="ok" />}
        </KpiStrip>
    )
}

function RuleCard({ rule }) {
    if (!rule) {
        return (
            <Section title={t('Rule')}>
                <p className="text-zinc-400">{t('The rule behind this incident was deleted.')}</p>
            </Section>
        )
    }

    return (
        <Section
            title={t('Rule')}
            actions={
                <Link href={rule.url} className="text-[0.6875rem] text-zinc-500 hover:text-zinc-800">
                    {t('Edit rule')}
                </Link>
            }
        >
            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                <dt className="text-zinc-500">{t('Name')}</dt>
                <dd className="text-zinc-900">{rule.name}</dd>
                <dt className="text-zinc-500">{t('Type')}</dt>
                <dd>{typeLabel(rule.type)}</dd>
                <dt className="text-zinc-500">{t('Signal')}</dt>
                <dd className="font-mono text-[0.6875rem]">{rule.signal}</dd>
                <dt className="text-zinc-500">{t('Condition')}</dt>
                <dd>{ruleSummary(rule)}</dd>
            </dl>
        </Section>
    )
}

export default function Incident({ incident, rule, timeline, context, back_url, resolve_url }) {
    const hasContext = Object.keys(context ?? {}).length > 0

    return (
        <OpsPage title={incident.rule_name ?? 'Incidents'}>
            <div className="space-y-3">
                <Heading incident={incident} backUrl={back_url} resolveUrl={resolve_url} />
                <Summary incident={incident} />

                <SectionGrid>
                    <Section title={t('Timeline')}>
                        <Timeline events={timeline} />
                    </Section>
                    <SectionStack>
                        <RuleCard rule={rule} />
                        <Section title={t('Context')}>
                            {hasContext ? (
                                <pre className="overflow-x-auto rounded bg-zinc-50 p-2 font-mono text-[0.6875rem] text-zinc-700">{JSON.stringify(context, null, 2)}</pre>
                            ) : (
                                <p className="text-zinc-400">{t('No context')}</p>
                            )}
                        </Section>
                    </SectionStack>
                </SectionGrid>
            </div>
        </OpsPage>
    )
}
