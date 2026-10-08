import { t } from '../i18n'

export function relative(iso, now = Date.now()) {
    if (!iso) {
        return null
    }

    const minutes = Math.round((new Date(iso).getTime() - now) / 60000)
    const size = Math.abs(minutes)
    const amount = size < 60 ? t(':count min', { count: size }) : size < 1440 ? t(':count h', { count: Math.round(size / 60) }) : t(':count d', { count: Math.round(size / 1440) })

    if (size === 0) {
        return t('now')
    }

    return minutes > 0 ? t('in :amount', { amount }) : t(':amount ago', { amount })
}
