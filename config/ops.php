<?php

declare(strict_types=1);

return [

    /*
    | Master switch. When false Ops records nothing, rolls nothing and serves no dashboard.
    */
    'enabled' => env('OPS_ENABLED', true),

    /*
    | The database connection the ops_* tables live on. Null uses the default connection.
    */
    'connection' => env('OPS_DB_CONNECTION'),

    /*
    | Dashboard URI prefix and the middleware it runs behind (Ops::check() is added on top).
    */
    'path' => env('OPS_PATH', 'ops'),

    'middleware' => ['web'],

    /*
    | The host Inertia page every screen renders (published by ops:install as
    | resources/js/pages/Ops.jsx). Set enabled to false to record without a dashboard.
    */
    'ui' => [
        'enabled' => true,
        'component' => 'Ops',
    ],

    /*
    | Hot-path buffer for request, outgoing and signal counters: one hash per minute.
    | `redis` needs Redis 6.2+ (ZADD GT). `array` keeps everything in memory (tests).
    */
    'buffer' => [
        'driver' => env('OPS_BUFFER', 'redis'),
        'connection' => env('OPS_REDIS_CONNECTION'),
        'prefix' => 'ops',
        'ttl_seconds' => 3 * 3600,
    ],

    /*
    | Register ops:rollup (every minute) and ops:prune (daily) on the host scheduler.
    */
    'schedule' => true,

    /*
    | Run origin stamped on every job a request dispatches: the first matching path pattern
    | wins. Scheduled tasks stamp `schedule`, artisan commands `console`.
    */
    'origins' => [
        'api/*' => 'api',
        '*' => 'web',
    ],

    /*
    | Defaults for every runtime setting. Anything here can be overridden from the settings
    | page; the stored value wins over this file.
    */
    'recorders' => [
        'requests' => [
            'enabled' => true,
            'paths' => ['*'],
            'ignore' => ['livewire/*', '_debugbar/*', 'telescope/*', 'horizon/*', 'pulse/*', 'up'],
        ],
        'outgoing' => [
            'enabled' => true,
            'ignore_hosts' => [],
        ],
        'jobs' => [
            'enabled' => true,
            'ignore' => [],
        ],
        'schedule' => [
            'enabled' => true,
        ],
        'security' => [
            'enabled' => true,
            'statuses' => [401, 403, 404, 419, 429],
        ],
    ],

    /*
    | Alert channels. Mail is on by default and sends through one of the host's mailers;
    | recipients, sender, mailer and the Slack webhook are set on the settings page.
    */
    'channels' => [
        'mail' => [
            'enabled' => true,
            'recipients' => [],
            'mailer' => null,
            'from_address' => null,
            'from_name' => null,
        ],
        'slack' => [
            'enabled' => false,
            'webhook_url' => null,
        ],
    ],

    /*
    | Language of notices and the dashboard (`en` or `th`). Null follows app()->getLocale().
    */
    'locale' => null,

    'signals' => [
        'user_route_floor' => 30,
    ],

    'retention' => [
        'minute_days' => 7,
        'hour_days' => 365,
        'user_days' => 30,
        'runs_days' => 30,
        'signal_days' => 7,
    ],

    /*
    | Setting keys stored encrypted in ops_settings.
    */
    'secret_settings' => [
        'channels.slack.webhook_url',
    ],

];
