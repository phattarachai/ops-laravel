<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Phattarachai\OpsLaravel\Ui\UiPage;

final class IncidentsController
{
    public function index(Request $request): Response
    {
        return UiPage::render('incidents');
    }

    public function show(Request $request, string $token): Response
    {
        return UiPage::render('incidents.show');
    }

    public function resolve(int $incident): RedirectResponse
    {
        return back();
    }
}
