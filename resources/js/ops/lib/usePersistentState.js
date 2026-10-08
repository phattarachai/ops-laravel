import { useState } from 'react'

function read(key, fallback) {
    try {
        const stored = localStorage.getItem(key)

        return stored === null ? fallback : JSON.parse(stored)
    } catch {
        return fallback
    }
}

export function usePersistentState(key, fallback) {
    const [value, setValue] = useState(() => read(key, fallback))

    function update(next) {
        setValue(next)

        try {
            localStorage.setItem(key, JSON.stringify(next))
        } catch {
            // storage unavailable — the value still applies for this page
        }
    }

    return [value, update]
}
