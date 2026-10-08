import { t } from '../../i18n'
import { number } from '../../lib/format'
import { Badge } from '../../ui/Badge'
import { DataTable } from '../../ui/DataTable'
import { countTone, urlWith } from './shared'
import { StatusHeading } from './StatusHeading'

export function UserName({ user }) {
    return (
        <span className="inline-flex min-w-0 items-center gap-1.5">
            <span className="truncate font-semibold text-zinc-900 group-hover:text-ops-accent-600">{user.name}</span>
            {user.segment && <Badge>{user.segment}</Badge>}
        </span>
    )
}

function statusColumn(key) {
    return {
        key,
        label: <StatusHeading status={key} />,
        align: 'right',
        render: (user) => <span className={countTone(key, user[key])}>{number(user[key])}</span>,
    }
}

function columns() {
    return [
        { key: 'name', label: t('User'), className: 'w-full max-w-0 min-w-48 overflow-hidden', render: (user) => <UserName user={user} /> },
        { key: 'detail', label: t('Detail'), className: 'text-zinc-500', render: (user) => user.detail ?? '—' },
        { key: 'requests', label: t('Requests'), align: 'right', render: (user) => number(user.requests) },
        statusColumn('s4xx'),
        statusColumn('s429'),
        statusColumn('s5xx'),
        { key: 'peak_per_minute', label: t('Peak/min'), align: 'right', render: (user) => number(user.peak_per_minute) },
        { key: 'avg_label', label: t('Average'), align: 'right', className: 'text-zinc-500' },
        { key: 'routes', label: t('Routes'), align: 'right', className: 'text-zinc-500' },
    ]
}

export function UsersTable({ users, selectedId, flush = false }) {
    return (
        <DataTable
            columns={columns()}
            rows={users}
            rowKey="user_id"
            rowHref={(user) => urlWith({ user: user.user_id })}
            rowClassName={(user) => (user.user_id === selectedId ? 'bg-zinc-50' : undefined)}
            empty={t('No signed-in users in this window')}
            flush={flush}
        />
    )
}
