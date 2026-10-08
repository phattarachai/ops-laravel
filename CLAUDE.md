# Ops Laravel — agent guide

## Scope

Self-hosted Laravel monitoring that replaces Pulse: request, job, schedule and outgoing-HTTP telemetry, rollups, an
Inertia React dashboard, and alerting by mail and Slack. This package owns the Laravel wiring — service provider,
config, recorders/listeners, storage on the `ops.connection` database, queue and schedule hooks, routes under
`ops.path`, the Inertia pages, and mail/Slack delivery. Dashboard access goes through `Ops::check()` (local → the
`Ops::auth()` callback → the `viewOps` gate).

Status: M1 skeleton only — provider, config, `Ops` auth surface and facade. No feature code yet.

## Core first

Framework-free logic lives in [`phattarachai/ops-core`](https://github.com/phattarachai/ops-core) (sibling checkout at
`/Users/phatchai/www/packages/ops-core`, wired here through a symlinked path repository). Before writing rollup math,
latency histogram bucketing/percentiles, alert rule evaluation or incident state transitions, put it in core. This
package keeps only the Laravel wiring: config → constructor arguments, DB/queue/cache, routes, Inertia, mail and Slack.

- Core is framework-free: no `illuminate/*`, no `config()`, no `now()`. Everything arrives through arguments; core's
  arch test enforces it.
- Order: core change → core release (tag + GitHub release, which pings Packagist) → bump `phattarachai/ops-core` here.
  Locally the path repository lets both move together, but CI resolves core from Packagist, so an unreleased core
  change turns this package's CI red.
- If a fix can't wait for a core release, ship it here and open a follow-up to move it into core. Don't leave a
  permanent fork of core logic in this package.

## Host safety

Ops runs inside the host's queue and request lifecycle, so follow the `laravel-package-development` rules for packages
that queue work: a configurable dedicated queue, never record Ops's own jobs/failures, bounded payloads, idempotent
upserts for rollups, and recording that can never throw into the host request.

## Tests and checks

```bash
composer install
vendor/bin/pest              # composer test — Testbench, sqlite :memory:
vendor/bin/pint              # composer format
vendor/bin/phpstan analyse   # composer analyse — larastan level 5, phpstan-baseline.neon
```
