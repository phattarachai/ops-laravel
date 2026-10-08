import { Link } from '@inertiajs/react'
import { ChevronDown, ChevronsUpDown, ChevronUp } from 'lucide-react'
import { Fragment, useState } from 'react'

import { cn } from '../lib/cn'
import { nextSort, sortRows } from '../lib/sortRows'
import { t } from '../i18n'

const ALIGN = { left: 'text-left', right: 'text-right', center: 'text-center' }

function keyOf(row, rowKey, index) {
    if (typeof rowKey === 'function') {
        return rowKey(row, index)
    }

    return row[rowKey] ?? index
}

function Cell({ column, row, href }) {
    const value = column.render ? column.render(row) : row[column.key]
    const title = column.truncate && typeof row[column.key] === 'string' ? row[column.key] : undefined

    return (
        <td className={cn('h-7 px-2 py-0.5', ALIGN[column.align ?? 'left'], column.truncate ? 'max-w-96 truncate' : 'whitespace-nowrap', column.className)} title={title}>
            {href ? (
                <Link href={href} className="after:absolute after:inset-0 group-hover:text-ops-accent-600">
                    {value}
                </Link>
            ) : (
                value
            )}
        </td>
    )
}

function Row({ row, columns, rowHref, rowClassName }) {
    const href = rowHref?.(row)

    return (
        <tr className={cn('border-b border-zinc-100 last:border-0 hover:bg-zinc-50', href && 'group relative', rowClassName?.(row))}>
            {columns.map((column, columnIndex) => (
                <Cell key={column.key} column={column} row={row} href={columnIndex === 0 ? href : undefined} />
            ))}
        </tr>
    )
}

function GroupRow({ group, columns }) {
    const className = 'h-6 border-b border-zinc-200 bg-zinc-50 px-2 text-[0.6875rem] font-semibold whitespace-nowrap text-zinc-600'
    const label = (
        <>
            {group.label}
            {group.hint && <span className="ml-1.5 font-normal text-zinc-400">{group.hint}</span>}
        </>
    )

    if (!group.summary) {
        return (
            <tr>
                <td colSpan={columns.length} className={className}>
                    {label}
                </td>
            </tr>
        )
    }

    return (
        <tr>
            {columns.map((column, index) => (
                <td key={column.key} className={cn(className, ALIGN[column.align ?? 'left'])}>
                    {index === 0 ? label : group.summary[column.key]}
                </td>
            ))}
        </tr>
    )
}

function SortIcon({ dir }) {
    const Icon = { asc: ChevronUp, desc: ChevronDown }[dir] ?? ChevronsUpDown

    return <Icon className={cn('size-3 shrink-0', dir ? 'text-zinc-700' : 'text-zinc-300 group-hover/sort:text-zinc-400')} />
}

function HeaderCell({ column, sort, onSort }) {
    const dir = sort?.key === column.key ? sort.dir : undefined
    const className = cn(
        'sticky top-0 border-b border-zinc-200 bg-zinc-50 px-2 py-1 text-[0.6875rem] font-semibold whitespace-nowrap text-zinc-500',
        ALIGN[column.align ?? 'left'],
    )

    if (!column.sortable) {
        return <th className={className}>{column.label}</th>
    }

    return (
        <th className={className} aria-sort={{ asc: 'ascending', desc: 'descending' }[dir] ?? 'none'}>
            <button
                type="button"
                onClick={() => onSort(column)}
                className={cn('group/sort inline-flex items-center gap-0.5 hover:text-zinc-800', column.align === 'right' && 'flex-row-reverse', dir && 'text-zinc-800')}
            >
                {column.label}
                <SortIcon dir={dir} />
            </button>
        </th>
    )
}

export function DataTable({ columns, rows = [], groups, rowKey = 'id', rowHref, rowClassName, empty = t('No data'), flush = false, className, sort, onSort, defaultSort = null }) {
    const [localSort, setLocalSort] = useState(defaultSort)
    const controlled = onSort !== undefined
    const activeSort = controlled ? sort : localSort
    const sortColumn = controlled ? null : columns.find((column) => column.sortable && column.key === activeSort?.key)
    const ordered = (list) => (sortColumn ? sortRows(list, sortColumn, activeSort.dir) : list)
    const rowProps = { columns, rowKey, rowHref, rowClassName }
    const isEmpty = groups ? groups.every((group) => group.rows.length === 0) : rows.length === 0

    function changeSort(column) {
        const next = nextSort(activeSort, column)

        if (controlled) {
            onSort(next.key, next.dir)

            return
        }

        setLocalSort(next)
    }

    return (
        <div className={cn('overflow-x-auto', !flush && 'rounded border border-zinc-200 bg-white', className)}>
            <table className="w-full border-collapse text-xs tabular-nums">
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <HeaderCell key={column.key} column={column} sort={activeSort} onSort={changeSort} />
                        ))}
                    </tr>
                </thead>
                <tbody className="text-zinc-700">
                    {isEmpty && (
                        <tr>
                            <td colSpan={columns.length} className="px-2 py-6 text-center text-zinc-400">
                                {empty}
                            </td>
                        </tr>
                    )}
                    {groups
                        ? groups
                              .filter((group) => group.rows.length > 0)
                              .map((group) => (
                                  <Fragment key={group.key}>
                                      <GroupRow group={group} columns={columns} />
                                      {ordered(group.rows).map((row, index) => (
                                          <Row key={keyOf(row, rowKey, index)} row={row} index={index} {...rowProps} />
                                      ))}
                                  </Fragment>
                              ))
                        : ordered(rows).map((row, index) => <Row key={keyOf(row, rowKey, index)} row={row} index={index} {...rowProps} />)}
                </tbody>
            </table>
        </div>
    )
}
