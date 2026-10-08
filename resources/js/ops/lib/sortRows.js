const collator = new Intl.Collator('th', { numeric: true, sensitivity: 'base' })

const isBlank = (value) => value === null || value === undefined || value === ''

function compare(a, b) {
    if (typeof a === 'number' && typeof b === 'number') {
        return a - b
    }

    return collator.compare(String(a), String(b))
}

export function sortValueOf(column, row) {
    return column.sortValue ? column.sortValue(row) : row[column.key]
}

export function sortRows(rows, column, dir) {
    const sign = dir === 'desc' ? -1 : 1
    const present = rows.filter((row) => !isBlank(sortValueOf(column, row)))
    const blank = rows.filter((row) => isBlank(sortValueOf(column, row)))

    return [...present.sort((a, b) => sign * compare(sortValueOf(column, a), sortValueOf(column, b))), ...blank]
}

export function nextSort(current, column) {
    if (current?.key === column.key) {
        return { key: column.key, dir: current.dir === 'asc' ? 'desc' : 'asc' }
    }

    return { key: column.key, dir: column.sortDir ?? 'asc' }
}
