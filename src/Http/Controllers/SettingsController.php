<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Phattarachai\OpsLaravel\Ui\UiPage;

final class SettingsController
{
    public function index(): Response
    {
        return UiPage::render('settings');
    }

    public function update(): RedirectResponse
    {
        return back();
    }

    public function test(string $channel): RedirectResponse
    {
        return back();
    }
}
