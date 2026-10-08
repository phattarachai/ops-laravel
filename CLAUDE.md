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

## Dashboard UI — conventions

- **One host page, many views.** Every screen is `UiPage::render('<view>', $props)`; the host's published stub
  (`stubs/Ops.jsx` → `resources/js/pages/Ops.jsx`) renders `<Ops>` from the `@ops` alias, and `views/index.js`
  maps the `view` prop to a component. Views wrap their content in `<OpsPage title toolbar>`, which uses the
  layout from context (default `OpsShell`, or the host's own shell passed as `<Ops layout={AppLayout}>`).
- **Kit:** `resources/js/ops/ui` (DataTable, Section, SectionGrid, TabSection, Pills, SearchInput, Dropdown,
  ActionMenu, Badge, Button, Card, Dialog, Icon, PageHeader, form) and `resources/js/ops/charts` (StackedBars,
  RangeBars, HourOfDay, Histogram, ValueBars, Kpi/KpiStrip, Legend, Sparkline, SparkBars, OutcomeSplit,
  Incidents, TimeFilter). Ported from f11-dr-backend's panel; keep their props and geometry.
- **Imports are relative** inside `resources/js/ops` (no `@/` alias — the host owns that). Bare imports allowed:
  `react`, `react-dom`, `@inertiajs/react`, `lucide-react` (the host provides them).
- **Colours:** only Tailwind's default palette plus the `ops-*` tokens in `resources/css/ops.css`
  (`ops-schedule/web/other/ink/fail/warn/ok/muted/grid`, `ops-accent[-600|-50]` for chrome). Red is failure only.
- **i18n:** write UI text in English through `t('English text', { var })` from `resources/js/ops/i18n`; add the
  Thai to the area's `i18n/th/<area>.js` (key = the English text). Never call `t()` at module scope — it runs before
  the locale is set; wrap column definitions in a function. Server-side labels go through `Format::date()`
  (Carbon `translatedFormat` in the configured locale) and `ops::` lang files.
- **Reports:** read models live in `src/Ui/Reports/<Area>/`, read through `OpsCache::remember()` (refresh busts
  it), single-pass over rows. SQL must run on pgsql, mysql and sqlite: branch on
  `$connection->getDriverName()` for `percentile_cont` / `date_trunc` style SQL and fall back to PHP math (ops-core
  `LatencyHistogram`, `Bucket`) elsewhere. Tests run on sqlite.
- **URLs** in props come from `route('ops.*')` plus `TimeFilter::params()`; tokens for classes/routes/tasks are
  `Format::base64url()`. An unknown token is a 404.
- Check the JS with `VITE_BIN=<host>/node_modules/vite/bin/vite.js bin/check-js`.
