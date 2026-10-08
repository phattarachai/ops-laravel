# Ops for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/phattarachai/ops-laravel.svg?style=flat-square)](https://packagist.org/packages/phattarachai/ops-laravel)
[![Tests](https://img.shields.io/github/actions/workflow/status/phattarachai/ops-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/phattarachai/ops-laravel/actions/workflows/run-tests.yml?query=branch%3Amain)
[![Code Style](https://img.shields.io/github/actions/workflow/status/phattarachai/ops-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/phattarachai/ops-laravel/actions/workflows/fix-php-code-style-issues.yml?query=branch%3Amain)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/phattarachai/ops-laravel/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/phattarachai/ops-laravel/actions/workflows/phpstan.yml?query=branch%3Amain)
[![PHP Version](https://img.shields.io/packagist/dependency-v/phattarachai/ops-laravel/php?style=flat-square&label=php&logo=php&logoColor=white)](https://packagist.org/packages/phattarachai/ops-laravel)
![Laravel Version](https://img.shields.io/badge/laravel-12%20%7C%2013-FF2D20?style=flat-square&logo=laravel&logoColor=white)
[![Total Downloads](https://img.shields.io/packagist/dt/phattarachai/ops-laravel.svg?style=flat-square)](https://packagist.org/packages/phattarachai/ops-laravel)

Self-hosted Laravel monitoring that replaces Pulse: request, job, schedule and outgoing-HTTP telemetry, rollups, an
Inertia React dashboard, and alerting by mail and Slack. The framework-free math (rollups, latency histograms, alert rule
evaluation, incident lifecycle) lives in [`phattarachai/ops-core`](https://github.com/phattarachai/ops-core).

> **Status: pre-release.** Nothing is tagged yet and the API can still change until `v0.1.0`.

## What it records

| Recorder | Source | Lands in |
| --- | --- | --- |
| Incoming requests | terminable middleware (after the response is sent) → Redis minute buffer | `ops_http_rollups` (route × method × status × segment, minute + hour, latency histogram), `ops_http_user_rollups` (user × route per hour) |
| Outgoing calls | Laravel `Http` client events — every client, no per-integration code | `ops_outgoing_rollups` (endpoint × method × status × outcome) |
| Jobs | queue events, one row per attempt (wait, origin, parent job, schedule run) | `ops_job_runs` |
| Schedule | scheduler events, incl. skipped runs and `->command()` child processes | `ops_schedule_runs` |
| Security | 401 / 403 / 404 / 419 / 429 per IP and user, failed logins | `ops_signal_*` (`http.403`, `auth.failed`, …) |
| Host signals | `Ops::signal('cross_tenant', key: "user:{$id}", meta: ['tenant' => $slug])` | `ops_signal_rollups`, `ops_signal_values` |
| Probes | `Ops::probe('network', 'vpn', ProbeLevel::Critical, message: 'down')` | `ops_probe_states` |

A recorder never breaks the request or job it measures: every write is wrapped and fails silently. `ops:rollup` (every
minute) rolls each closed minute exactly once, `ops:alerts` (every minute, right after) evaluates the rules, and
`ops:prune` (daily) applies retention. All three are put on the scheduler for you.

## Install

```bash
composer require phattarachai/ops-laravel
php artisan migrate
php artisan ops:install      # config, the Ops page stub, @ops Vite alias, Tailwind wiring, default alert rules
npm run build
```

Requirements: Laravel 12 or 13, Inertia 2/3 with React, Tailwind v4, Redis 6.2+ for the buffer (`OPS_BUFFER=array`
keeps it in memory, for tests).

Migrations skip any table that already exists, so a host that built its own `ops_*` tables keeps its history.

## Dashboard

`/ops` (config `ops.path`): Overview, Incoming, Outgoing, Jobs, Schedules, Incidents, Rules and Settings. Every screen
renders the published `resources/js/pages/Ops.jsx`, which you can edit to render inside your own layout:

```jsx
import { Ops } from '@ops'
import { AppLayout } from '@/layouts/AppLayout'

export default (props) => <Ops {...props} layout={AppLayout} />   // any component taking { toolbar, children }
```

Text is English or Thai (`ops.locale`, default `app()->getLocale()`).

### Access

Local is always allowed. Elsewhere register a callback, or define a `viewOps` gate:

```php
Ops::auth(fn (Request $request) => $request->user()?->isAdmin() ?? false);
```

## Alerts

Rules live in `ops_rules` and are edited on the Rules page. Types: `threshold` (count per key in a window, or distinct
values of a meta field), `rate` (one signal as a share of another), `absence` (silent for N seconds) and `state` (a probe
at warning / critical). An incident opens once per rule × key, re-notifies after the cooldown (at once when it
escalates), and resolves after the rule has been clear for a while. Built-in signals: `job.run`, `job.failed`,
`schedule.run`, `schedule.failed`, `outgoing.call`, `outgoing.failed`, `http.user_route`, `http.{status}`, `auth.failed`.

`ops:install` seeds starting rules (production only): 403 ≥ 20 and 429 ≥ 10 per IP/user in 5 minutes, cross-tenant
probing ≥ 3 distinct tenants in 10 minutes, failed logins, one user flooding one route, repeated job failures.

Channels are configured on the Settings page, each with a **Send test** button:

- **Mail** (on by default) — recipients, sender and one of your `config('mail.mailers')`.
- **Slack** — an incoming-webhook URL, stored encrypted.
- Your own: `Ops::channel('telegram', fn () => new TelegramChannel)` implementing `Phattarachai\OpsCore\Alerts\Contracts\IncidentChannel`.

## Host hooks

```php
Ops::segmentUsing(fn (User $user) => $user->role->value);          // the "segment" requests are split by
Ops::outgoingEndpointUsing(fn ($request) => 'vendor.orders');       // name outgoing calls (default: host)
Ops::resolveUsersUsing(fn (array $ids) => [...]);                   // id => ['name' => …, 'detail' => …, 'url' => …]
Ops::overviewBlocks(fn (TimeRange $range) => [...]);                // extra Overview status blocks
Ops::navLinks([['label' => 'Horizon', 'url' => url('horizon')]]);   // extra sidebar links
Ops::withoutRecording(fn () => ...);                                // skip telemetry for a block
```

Runtime settings (recorders on/off, ignore lists, retention, channels) are stored in `ops_settings`; a stored value wins
over the `config/ops.php` default of the same key.

## Development

`composer.json` points `phattarachai/ops-core` at a sibling checkout through a path repository (symlinked), so core and
this package can change together:

```
~/www/packages/
├── ops-core/
└── ops-laravel/
```

```bash
composer install
vendor/bin/pest              # composer test
vendor/bin/pint              # composer format
vendor/bin/phpstan analyse   # composer analyse — larastan level 5, baselined
```

CI drops that path repository (`composer config --unset repositories.ops-core`) and resolves `phattarachai/ops-core`
from Packagist instead, so **CI stays red until ops-core is published** on GitHub and Packagist with a `v0.1.x` tag.

## License

MIT. See [LICENSE](LICENSE).
