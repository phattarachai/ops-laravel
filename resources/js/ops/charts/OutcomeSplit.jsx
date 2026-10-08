import { Badge } from '../ui'
import { cn } from '../lib/cn'
import { number } from '../lib/format'
import { t } from '../i18n'

const BAR = { ok: 'bg-ops-ok/40', warn: 'bg-ops-warn', fail: 'bg-ops-fail', muted: 'bg-ops-muted' }
const BADGE = { ok: 'green', warn: 'amber', fail: 'red', muted: 'zinc' }

export function OutcomeSplit({ outcomes, labels = {} }) {
    const visible = outcomes.filter((outcome) => outcome.count > 0)
    const total = visible.reduce((sum, outcome) => sum + outcome.count, 0)

    if (total === 0) {
        return <p className="py-6 text-center text-zinc-400">{t('No calls in this window')}</p>
    }

    return (
        <div className="space-y-2">
            <div className="flex h-2.5 overflow-hidden rounded-sm bg-zinc-100">
                {visible.map((outcome) => (
                    <span key={outcome.kind} title={`${labels[outcome.kind] ?? outcome.kind} ${number(outcome.count)}`} className={cn(BAR[outcome.tone])} style={{ width: `${(outcome.count / total) * 100}%` }} />
                ))}
            </div>
            <table className="w-full text-xs tabular-nums">
                <tbody>
                    {visible.map((outcome) => (
                        <tr key={outcome.kind} className="border-b border-zinc-100 last:border-0">
                            <td className="h-6">
                                <Badge tone={BADGE[outcome.tone]}>{labels[outcome.kind] ?? outcome.kind}</Badge>
                            </td>
                            <td className="text-right">{number(outcome.count)}</td>
                            <td className="w-16 text-right text-zinc-500">{((outcome.count / total) * 100).toFixed(2)}%</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    )
}
