import { MoreHorizontal } from 'lucide-react'
import { useCallback, useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'

import { cn } from '../lib/cn'
import { t } from '../i18n'

const MENU_HEIGHT_GUESS = 160

function placement(rect) {
    const opensUp = rect.bottom + MENU_HEIGHT_GUESS > window.innerHeight

    return {
        right: window.innerWidth - rect.right,
        ...(opensUp ? { bottom: window.innerHeight - rect.top + 4 } : { top: rect.bottom + 4 }),
    }
}

function useDismiss(open, button, panel, close) {
    useEffect(() => {
        if (!open) {
            return undefined
        }

        function onPointer(event) {
            if (!button.current?.contains(event.target) && !panel.current?.contains(event.target)) {
                close()
            }
        }

        function onKey(event) {
            if (event.key === 'Escape') {
                close()
            }
        }

        document.addEventListener('mousedown', onPointer)
        document.addEventListener('keydown', onKey)
        window.addEventListener('scroll', close, true)
        window.addEventListener('resize', close)

        return () => {
            document.removeEventListener('mousedown', onPointer)
            document.removeEventListener('keydown', onKey)
            window.removeEventListener('scroll', close, true)
            window.removeEventListener('resize', close)
        }
    }, [open, button, panel, close])
}

export function ActionMenu({ items, title = t('Actions') }) {
    const [position, setPosition] = useState(null)
    const button = useRef(null)
    const panel = useRef(null)
    const close = useCallback(() => setPosition(null), [])

    useDismiss(position !== null, button, panel, close)

    function toggle() {
        setPosition((current) => (current ? null : placement(button.current.getBoundingClientRect())))
    }

    return (
        <>
            <button
                ref={button}
                type="button"
                onClick={toggle}
                aria-haspopup="menu"
                aria-expanded={position !== null}
                aria-label={title}
                title={title}
                className={cn('inline-flex size-6 items-center justify-center rounded text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800', position && 'bg-zinc-100 text-zinc-800')}
            >
                <MoreHorizontal className="size-4" />
            </button>
            {position &&
                createPortal(
                    <div ref={panel} role="menu" style={{ position: 'fixed', ...position }} className="z-50 min-w-40 rounded border border-zinc-200 bg-white py-1 text-left shadow-lg">
                        {items.map((item) => {
                            const Icon = item.icon

                            return (
                                <button
                                    key={item.label}
                                    type="button"
                                    role="menuitem"
                                    onClick={() => {
                                        close()
                                        item.onSelect()
                                    }}
                                    className={cn(
                                        'flex w-full items-center gap-2 px-2.5 py-1.5 text-xs whitespace-nowrap',
                                        item.tone === 'danger' ? 'text-red-600 hover:bg-red-50' : 'text-zinc-700 hover:bg-zinc-50',
                                    )}
                                >
                                    {Icon && <Icon className="size-3.5 shrink-0" />}
                                    {item.label}
                                </button>
                            )
                        })}
                    </div>,
                    document.body,
                )}
        </>
    )
}
