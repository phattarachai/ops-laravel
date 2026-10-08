import { statusTitle } from './shared'

const LABELS = { s4xx: '4xx', s429: '429', s5xx: '5xx' }

export function StatusHeading({ status }) {
    return <span title={statusTitle(status)}>{LABELS[status]}</span>
}
