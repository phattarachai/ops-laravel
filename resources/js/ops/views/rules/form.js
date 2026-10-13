const minutes = (value) => (value === null || value === undefined ? '' : String(Math.round((value / 60) * 100) / 100))

const seconds = (value) => (value === '' || value === null || value === undefined ? null : Math.round(Number(value) * 60))

const list = (text) =>
    String(text ?? '')
        .split(/[,\n]+/)
        .map((item) => item.trim())
        .filter(Boolean)

export function blankDraft() {
    return {
        name: '',
        type: 'threshold',
        signal: '',
        severity: 'warning',
        threshold: '',
        window_minutes: '5',
        silent_minutes: '15',
        distinct_field: '',
        denominator_signal: '',
        min_samples: '10',
        min_level: 'warning',
        keys_text: '',
        environments_text: '',
        channels: [],
        cooldown_minutes: '15',
        resolve_after_minutes: '5',
        active: true,
        notify_on_resolve: true,
        remind_warnings: true,
    }
}

export function toDraft(rule) {
    return {
        name: rule.name,
        type: rule.type,
        signal: rule.signal,
        severity: rule.severity,
        threshold: rule.type === 'absence' || rule.type === 'state' ? '' : String(rule.threshold),
        window_minutes: minutes(rule.window_seconds),
        silent_minutes: rule.type === 'absence' ? minutes(rule.threshold) : '15',
        distinct_field: rule.distinct_field ?? '',
        denominator_signal: rule.denominator_signal ?? '',
        min_samples: String(rule.min_samples ?? 1),
        min_level: rule.min_level ?? 'warning',
        keys_text: (rule.keys ?? []).join(', '),
        environments_text: (rule.environments ?? []).join(', '),
        channels: rule.channels ?? [],
        cooldown_minutes: minutes(rule.cooldown_seconds),
        resolve_after_minutes: minutes(rule.resolve_after_seconds),
        active: rule.active,
        notify_on_resolve: rule.notify_on_resolve,
        remind_warnings: rule.remind_warnings ?? true,
    }
}

export function toPayload(draft) {
    return {
        name: draft.name,
        type: draft.type,
        signal: draft.signal,
        severity: draft.severity,
        threshold: draft.type === 'absence' ? seconds(draft.silent_minutes) : draft.type === 'state' ? null : draft.threshold,
        window_seconds: draft.type === 'threshold' || draft.type === 'rate' ? seconds(draft.window_minutes) : null,
        distinct_field: draft.type === 'threshold' ? draft.distinct_field : null,
        denominator_signal: draft.type === 'rate' ? draft.denominator_signal : null,
        min_samples: draft.type === 'rate' ? draft.min_samples : null,
        min_level: draft.type === 'state' ? draft.min_level : null,
        keys: list(draft.keys_text),
        environments: list(draft.environments_text),
        channels: draft.channels,
        cooldown_seconds: seconds(draft.cooldown_minutes) ?? 0,
        resolve_after_seconds: seconds(draft.resolve_after_minutes) ?? 0,
        active: draft.active,
        notify_on_resolve: draft.notify_on_resolve,
        remind_warnings: draft.remind_warnings,
    }
}

export function quickUpdate(rule, changes) {
    return { ...toPayload(toDraft(rule)), ...changes }
}

export const ERROR_FIELDS = {
    threshold: ['threshold'],
    window_minutes: ['window_seconds'],
    silent_minutes: ['threshold'],
    keys_text: ['keys'],
    environments_text: ['environments'],
    cooldown_minutes: ['cooldown_seconds'],
    resolve_after_minutes: ['resolve_after_seconds'],
}

export function errorFor(errors, field) {
    const names = ERROR_FIELDS[field] ?? [field]
    const key = Object.keys(errors).find((name) => names.some((candidate) => name === candidate || name.startsWith(`${candidate}.`)))

    return key ? errors[key] : undefined
}
