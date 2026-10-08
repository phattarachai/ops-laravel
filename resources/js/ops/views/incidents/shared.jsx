import { t } from '../../i18n'
import { Badge } from '../../ui/Badge'

const SEVERITY_TONES = { critical: 'red', warning: 'amber' }

export function SeverityBadge({ severity }) {
    return <Badge tone={SEVERITY_TONES[severity] ?? 'zinc'}>{t(severity === 'critical' ? 'Critical' : 'Warning')}</Badge>
}

export function StatusBadge({ open }) {
    return open ? <Badge tone="red">{t('Open')}</Badge> : <Badge tone="green">{t('Resolved')}</Badge>
}
