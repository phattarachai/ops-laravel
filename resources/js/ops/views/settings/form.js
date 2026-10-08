export const LIST_FIELDS = ['channels.mail.recipients', 'recorders.requests.paths', 'recorders.requests.ignore', 'recorders.outgoing.ignore_hosts', 'recorders.jobs.ignore']

export const STATUS_FIELD = 'recorders.security.statuses'

export function get(data, path) {
    return path.split('.').reduce((value, key) => value?.[key], data)
}

export function assign(data, path, value) {
    const [key, ...rest] = path.split('.')

    return { ...data, [key]: rest.length === 0 ? value : assign(data?.[key] ?? {}, rest.join('.'), value) }
}

const lines = (text) =>
    String(text ?? '')
        .split(/[\n,]+/)
        .map((line) => line.trim())
        .filter(Boolean)

export function toDraft(settings) {
    let draft = assign(settings, 'channels.slack', { ...settings.channels.slack, webhook_url: '', clear_webhook: false })

    LIST_FIELDS.forEach((path) => {
        draft = assign(draft, path, (get(settings, path) ?? []).join('\n'))
    })

    return assign(draft, STATUS_FIELD, (get(settings, STATUS_FIELD) ?? []).join(', '))
}

export function toPayload(draft) {
    let payload = draft

    LIST_FIELDS.forEach((path) => {
        payload = assign(payload, path, lines(get(draft, path)))
    })

    return assign(
        payload,
        STATUS_FIELD,
        lines(get(draft, STATUS_FIELD)).map((status) => Number(status)),
    )
}

export function fieldError(errors, path) {
    const key = Object.keys(errors).find((name) => name === path || name.startsWith(`${path}.`))

    return key ? errors[key] : undefined
}
