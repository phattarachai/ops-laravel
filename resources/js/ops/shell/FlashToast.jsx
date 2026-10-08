import { usePage } from '@inertiajs/react'
import { CircleAlert, CircleCheck, Info, TriangleAlert, X } from 'lucide-react'
import { useEffect, useState } from 'react'

import { cn } from '../lib/cn'
import { t } from '../i18n'

const TONES = {
    success: { icon: CircleCheck, accent: 'border-l-green-500', iconClass: 'text-green-600', seconds: 4 },
    info: { icon: Info, accent: 'border-l-blue-500', iconClass: 'text-blue-600', seconds: 4 },
    warning: { icon: TriangleAlert, accent: 'border-l-amber-500', iconClass: 'text-amber-600', seconds: 6 },
    error: { icon: CircleAlert, accent: 'border-l-red-500', iconClass: 'text-red-600', seconds: 8 },
}

const SLIDE_MS = 250

function normalise(flash) {
    if (!flash) {
        return null
    }

    return typeof flash === 'string' ? { message: flash, tone: 'success' } : { message: flash.message, tone: TONES[flash.tone] ? flash.tone : 'success' }
}

export function FlashToast() {
    const { flash } = usePage().props
    const [toast, setToast] = useState(null)

    const patch = (id, changes) => setToast((current) => (current?.id === id ? { ...current, ...changes } : current))

    useEffect(() => {
        const next = normalise(flash?.message)

        if (next?.message) {
            setToast({ ...next, id: Date.now(), entered: false, leaving: false })
        }
    }, [flash])

    useEffect(() => {
        if (!toast?.id) {
            return undefined
        }

        const { id, tone } = toast
        const frame = requestAnimationFrame(() => requestAnimationFrame(() => patch(id, { entered: true })))
        const timer = setTimeout(() => patch(id, { leaving: true }), TONES[tone].seconds * 1000)

        return () => {
            cancelAnimationFrame(frame)
            clearTimeout(timer)
        }
    }, [toast?.id])

    useEffect(() => {
        if (!toast?.leaving) {
            return undefined
        }

        const timer = setTimeout(() => setToast((current) => (current?.leaving ? null : current)), SLIDE_MS)

        return () => clearTimeout(timer)
    }, [toast?.leaving])

    if (!toast) {
        return null
    }

    const tone = TONES[toast.tone]
    const Icon = tone.icon
    const visible = toast.entered && !toast.leaving

    return (
        <div
            key={toast.id}
            role="status"
            className={cn(
                'fixed right-3 bottom-3 z-50 flex max-w-sm items-start gap-2 rounded-md border border-l-4 border-zinc-200 bg-white py-2 pr-2 pl-3 text-xs text-zinc-800 shadow-lg transition duration-250 ease-out motion-reduce:transition-none',
                visible ? 'translate-x-0 opacity-100' : 'pointer-events-none translate-x-[calc(100%+1rem)] opacity-0',
                tone.accent,
            )}
        >
            <Icon className={cn('mt-px size-4 shrink-0', tone.iconClass)} />
            <span className="flex-1 leading-5">{toast.message}</span>
            <button type="button" onClick={() => patch(toast.id, { leaving: true })} className="rounded p-0.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700" aria-label={t('Close')}>
                <X className="size-3.5" />
            </button>
        </div>
    )
}
