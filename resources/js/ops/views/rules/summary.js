import { t } from '../../i18n'
import { duration } from '../../lib/chart'

export function typeLabel(type) {
    return {
        threshold: t('Threshold'),
        rate: t('Rate'),
        absence: t('Absence'),
        state: t('State'),
    }[type] ?? type
}

export function ruleSummary(rule) {
    const window = duration(rule.window_seconds)

    switch (rule.type) {
        case 'rate':
            return t('≥ :threshold% of :denominator in :window', { threshold: rule.threshold, denominator: rule.denominator_signal ?? rule.signal, window })
        case 'absence':
            return t('silent for ≥ :duration', { duration: duration(rule.threshold) })
        case 'state':
            return t('level ≥ :level', { level: t(rule.min_level === 'critical' ? 'Critical' : 'Warning') })
        default:
            return rule.distinct_field
                ? t('≥ :threshold distinct :field in :window', { threshold: rule.threshold, field: rule.distinct_field, window })
                : t('≥ :threshold in :window', { threshold: rule.threshold, window })
    }
}
