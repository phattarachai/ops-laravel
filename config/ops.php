<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch. When false, Ops records nothing and serves no dashboard,
    | while the package stays installed and its config stays published.
    |
    */

    'enabled' => env('OPS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Database connection
    |--------------------------------------------------------------------------
    |
    | The connection Ops stores its telemetry and rollups on. Null uses the
    | application's default connection; point it at a separate database to
    | keep monitoring writes off the primary.
    |
    */

    'connection' => env('OPS_DB_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard path
    |--------------------------------------------------------------------------
    |
    | The URI prefix the Ops dashboard is served under, e.g. /ops. Access is
    | decided by Ops::check(): local environments are open, otherwise the
    | Ops::auth() callback or the `viewOps` gate.
    |
    */

    'path' => env('OPS_PATH', 'ops'),

];
