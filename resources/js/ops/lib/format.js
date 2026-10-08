const NUMBER = new Intl.NumberFormat('en-US')

export function number(value) {
    return value === null || value === undefined ? '—' : NUMBER.format(value)
}
