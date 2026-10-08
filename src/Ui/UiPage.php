<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Every dashboard screen renders the one host page component (`Ops` by default, the stub that
 * ops:install publishes) with a `view` prop naming the screen, plus the shared `ops` prop.
 */
final class UiPage
{
    /** @param  array<string, mixed>  $props */
    public static function render(string $view, array $props = []): Response
    {
        return Inertia::render((string) config('ops.ui.component', 'Ops'), [
            ...$props,
            'view' => $view,
            'ops' => [
                'locale' => Format::locale(),
                'nav' => Nav::items(),
                'home_url' => route('ops.overview'),
                'app_name' => config('app.name'),
                'environment' => app()->environment(),
            ],
        ]);
    }
}
