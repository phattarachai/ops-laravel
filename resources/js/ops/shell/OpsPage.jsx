import { Head } from '@inertiajs/react'

import { t } from '../i18n'
import { useLayout } from './LayoutContext'

export function OpsPage({ title, toolbar, children }) {
    const Layout = useLayout()

    return (
        <>
            <Head title={title ? `${t(title)} · Ops` : 'Ops'} />
            <Layout toolbar={toolbar}>{children}</Layout>
        </>
    )
}
