import { t } from '../../i18n'

export function RouteName({ route, uri, methods, showName = false }) {
    if (!uri) {
        return (
            <>
                <span className="font-semibold text-zinc-900 group-hover:text-ops-accent-600">{route}</span>
                <span className="ml-1.5 text-[0.6875rem] text-zinc-400">{t('matched no route')}</span>
            </>
        )
    }

    return (
        <>
            {methods && <span className="mr-1.5 font-medium text-zinc-500">{methods}</span>}
            <span className="font-semibold text-zinc-900 group-hover:text-ops-accent-600">{uri}</span>
            {showName && <span className="ml-1.5 text-[0.6875rem] text-zinc-400">{route}</span>}
        </>
    )
}
