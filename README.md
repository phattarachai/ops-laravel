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

> **Status: pre-release, under construction.** Nothing is tagged yet; only the package skeleton exists. Config keys,
> routes and the API will change without notice until `v0.1.0`. Don't install it in production.

## Install

```bash
composer require phattarachai/ops-laravel
php artisan vendor:publish --tag=ops-config
```

The service provider and the `Ops` facade alias are auto-discovered.

## Configuration

| Env | Default | Purpose |
| --- | --- | --- |
| `OPS_ENABLED` | `true` | Master switch. |
| `OPS_DB_CONNECTION` | app default | Connection Ops stores telemetry and rollups on. |
| `OPS_PATH` | `ops` | URI prefix of the dashboard. |

## Dashboard access

Local environments are always allowed. Elsewhere, register a callback in a service provider:

```php
use Illuminate\Http\Request;
use Phattarachai\OpsLaravel\Ops;

Ops::auth(fn (Request $request) => $request->user()?->isAdmin() ?? false);
```

Without a callback, Ops falls back to a `viewOps` gate, and denies when none is defined:

```php
Gate::define('viewOps', fn ($user) => $user->isAdmin());
```

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
