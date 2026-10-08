import th from './th'

const DICTIONARIES = { th }

let current = 'en'

export function setLocale(next) {
    current = DICTIONARIES[next] ? next : 'en'
}

export function locale() {
    return current
}

export function t(text, vars = {}) {
    const template = DICTIONARIES[current]?.[text] ?? text

    return template.replace(/:([a-z_]+)/gi, (match, key) => (key in vars ? String(vars[key]) : match))
}
