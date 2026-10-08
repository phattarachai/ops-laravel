import { Link } from '@inertiajs/react'

import { t } from '../../i18n'
import { number } from '../../lib/format'
import { relative } from '../../lib/time'
import { Section } from '../../ui/Section'
import { RouteName } from './RouteName'
import { urlWith } from './shared'
import { UserName } from './UsersTable'

export function HotUsers({ users, threshold }) {
    if (users.length === 0) {
        return null
    }

    return (
        <Section title={t('Users over the rate threshold')} tooltip={t('≥ :count requests per minute on one route', { count: number(threshold) })} flush className="border-amber-200">
            <table className="w-full text-xs tabular-nums">
                <tbody className="text-zinc-700">
                    {users.map((hot) => (
                        <tr key={`${hot.user_id}-${hot.route}-${hot.at}`} className="border-b border-zinc-100 last:border-0">
                            <td className="h-7 px-2.5">
                                <Link href={urlWith({ user: hot.user_id })} className="group" preserveScroll>
                                    <UserName user={hot} />
                                </Link>
                            </td>
                            <td className="max-w-80 truncate px-2">
                                <Link href={hot.route_url} className="group">
                                    <RouteName route={hot.route} uri={hot.uri} />
                                </Link>
                            </td>
                            <td className="px-2 text-right font-semibold whitespace-nowrap text-ops-warn">{t(':count / min', { count: number(hot.peak_per_minute) })}</td>
                            <td className="px-2 text-right whitespace-nowrap text-zinc-500">{t(':count requests', { count: number(hot.requests) })}</td>
                            <td className="px-2.5 text-right whitespace-nowrap text-zinc-500" title={relative(hot.at) ?? undefined}>
                                {hot.period_label}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </Section>
    )
}
