import { X } from 'lucide-react'
import { useEffect, useRef } from 'react'
import { createPortal } from 'react-dom'

import { cn } from '../lib/cn'
import { t } from '../i18n'

const FOCUSABLE = 'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])'

function useDialogKeys(open, onClose, body) {
    const close = useRef(onClose)

    useEffect(() => {
        close.current = onClose
    })

    useEffect(() => {
        if (!open) {
            return undefined
        }

        body.current?.querySelector(FOCUSABLE)?.focus()

        function onKey(event) {
            if (event.key === 'Escape') {
                close.current()
            }
        }

        document.addEventListener('keydown', onKey)

        return () => document.removeEventListener('keydown', onKey)
    }, [open, body])
}

export function Dialog({ open, onClose, title, footer, className, children }) {
    const body = useRef(null)

    useDialogKeys(open, onClose, body)

    if (!open) {
        return null
    }

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-zinc-900/40 p-4 pt-[10vh]" onMouseDown={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-label={title}
                className={cn('flex max-h-[80vh] w-full max-w-lg flex-col rounded border border-zinc-200 bg-white text-xs shadow-xl', className)}
                onMouseDown={(event) => event.stopPropagation()}
            >
                <header className="flex min-h-9 items-center gap-2 border-b border-zinc-100 px-3">
                    <h2 className="text-[0.8125rem] font-semibold text-zinc-800">{title}</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label={t('Close')}
                        className="ml-auto inline-flex size-6 items-center justify-center rounded text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800"
                    >
                        <X className="size-4" />
                    </button>
                </header>
                <div ref={body} className="min-h-0 flex-1 overflow-y-auto p-3">
                    {children}
                </div>
                {footer && <footer className="flex items-center justify-end gap-2 border-t border-zinc-100 px-3 py-2">{footer}</footer>}
            </div>
        </div>,
        document.body,
    )
}
