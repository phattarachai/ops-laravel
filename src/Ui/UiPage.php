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
    private const string FLASH_KEY = 'ops.flash';

    /** @param  array<string, mixed>  $props */
    public static function render(string $view, array $props = []): Response
    {
        return Inertia::render((string) config('ops.ui.component', 'Ops'), [
            ...$props,
            ...self::flash(),
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

    /**
     * A toast for the next page, translated through the package's JSON lang files.
     *
     * @param  array<string, string|int>  $vars
     */
    public static function toast(string $text, array $vars = [], string $tone = 'success'): void
    {
        session()->flash(self::FLASH_KEY, ['message' => __($text, $vars, Format::locale()), 'tone' => $tone]);
    }

    /** @return array{flash?: array{message: array{message: string, tone: string}}} */
    private static function flash(): array
    {
        $flash = session(self::FLASH_KEY);

        return is_array($flash) ? ['flash' => ['message' => $flash]] : [];
    }
}
