import { useState } from 'react'

function storageKey(panelId) {
    return `panel-nav-collapsed:${panelId}`
}

function read(panelId) {
    try {
        return JSON.parse(localStorage.getItem(storageKey(panelId)) ?? '[]')
    } catch {
        return []
    }
}

function write(panelId, groups) {
    try {
        localStorage.setItem(storageKey(panelId), JSON.stringify(groups))
    } catch {
        // storage unavailable — collapsing still works for this page
    }
}

export function useCollapsedGroups(panelId) {
    const [collapsed, setCollapsed] = useState(() => read(panelId))

    function toggle(group) {
        setCollapsed((previous) => {
            const next = previous.includes(group) ? previous.filter((entry) => entry !== group) : [...previous, group]
            write(panelId, next)

            return next
        })
    }

    return { isCollapsed: (group) => collapsed.includes(group), toggle }
}
