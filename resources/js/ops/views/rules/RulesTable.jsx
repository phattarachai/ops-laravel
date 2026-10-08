import { router } from '@inertiajs/react'
import { BellOff, BellRing, Pause, Pencil, Play, Trash2 } from 'lucide-react'

import { t } from '../../i18n'
import { relative } from '../../lib/time'
import { ActionMenu } from '../../ui/ActionMenu'
import { Badge } from '../../ui/Badge'
import { DataTable } from '../../ui/DataTable'
import { SeverityBadge } from '../incidents/shared'
import { quickUpdate } from './form'
import { ruleSummary, typeLabel } from './summary'

const visit = { preserveScroll: true }

function actions(rule, onEdit) {
    const items = [{ label: t('Edit'), icon: Pencil, onSelect: () => onEdit(rule) }]

    if (rule.is_muted) {
        items.push({ label: t('Unmute'), icon: BellRing, onSelect: () => router.put(rule.update_url, quickUpdate(rule, { mute: 'clear' }), visit) })
    } else {
        items.push({ label: t('Mute for 1 hour'), icon: BellOff, onSelect: () => router.put(rule.update_url, quickUpdate(rule, { mute: '1h' }), visit) })
        items.push({ label: t('Mute for 24 hours'), icon: BellOff, onSelect: () => router.put(rule.update_url, quickUpdate(rule, { mute: '24h' }), visit) })
    }

    items.push(
        rule.active
            ? { label: t('Pause'), icon: Pause, onSelect: () => router.put(rule.update_url, quickUpdate(rule, { active: false }), visit) }
            : { label: t('Activate'), icon: Play, onSelect: () => router.put(rule.update_url, quickUpdate(rule, { active: true }), visit) },
    )

    items.push({
        label: t('Delete'),
        icon: Trash2,
        tone: 'danger',
        onSelect: () => {
            if (window.confirm(t('Delete the rule ":name"?', { name: rule.name }))) {
                router.delete(rule.destroy_url, visit)
            }
        },
    })

    return items
}

function State({ rule }) {
    if (!rule.active) {
        return <Badge>{t('Paused')}</Badge>
    }

    if (rule.is_muted) {
        return (
            <Badge tone="amber">
                <span title={rule.muted_until}>{t('Muted :time', { time: relative(rule.muted_until_iso) })}</span>
            </Badge>
        )
    }

    return <Badge tone="green">{t('Active')}</Badge>
}

const listOrAll = (items, all) => (items.length === 0 ? <span className="text-zinc-400">{all}</span> : items.join(', '))

function columns(onEdit) {
    return [
        {
            key: 'name',
            label: t('Rule'),
            className: 'w-full max-w-0 min-w-56 overflow-hidden',
            sortable: true,
            render: (rule) => (
                <button type="button" onClick={() => onEdit(rule)} className="block min-w-56 text-left">
                    <span className="block truncate font-semibold text-zinc-900 hover:text-ops-accent-600">{rule.name}</span>
                    <span className="block truncate font-mono text-[0.6875rem] text-zinc-500">{rule.signal}</span>
                </button>
            ),
        },
        { key: 'type', label: t('Type'), sortable: true, render: (rule) => typeLabel(rule.type) },
        { key: 'condition', label: t('Condition'), render: (rule) => ruleSummary(rule) },
        { key: 'severity', label: t('Severity'), sortable: true, render: (rule) => (rule.type === 'state' ? <span className="text-zinc-400">{t('by level')}</span> : <SeverityBadge severity={rule.severity} />) },
        { key: 'channels', label: t('Channels'), render: (rule) => listOrAll(rule.channels, t('All enabled')) },
        { key: 'environments', label: t('Environments'), render: (rule) => listOrAll(rule.environments, t('All')) },
        { key: 'state', label: t('State'), render: (rule) => <State rule={rule} /> },
        {
            key: 'open_incidents',
            label: t('Open'),
            align: 'right',
            sortable: true,
            sortDir: 'desc',
            render: (rule) => (rule.open_incidents > 0 ? <span className="font-semibold text-ops-fail">{rule.open_incidents}</span> : <span className="text-zinc-300">0</span>),
        },
        { key: 'actions', label: '', align: 'right', render: (rule) => <ActionMenu items={actions(rule, onEdit)} /> },
    ]
}

export function RulesTable({ rules, onEdit }) {
    return <DataTable columns={columns(onEdit)} rows={rules} empty={t('No rules yet')} />
}
