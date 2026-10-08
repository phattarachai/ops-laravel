import { Ops } from '@ops'

/**
 * Published by `php artisan ops:install`. Inertia's page glob only looks in ./pages, so the
 * package's screens are reached through the `@ops` Vite alias from here.
 *
 * Pass `layout` to render inside your own app shell instead of Ops' default one:
 * any component taking `{ toolbar, children }` works, e.g. `<Ops {...props} layout={AppLayout} />`.
 */
export default function OpsPage(props) {
    return <Ops {...props} />
}
