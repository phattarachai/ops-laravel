<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Response;
use Phattarachai\OpsLaravel\Ui\UiPage;

final class RequestsController
{
    public function index(Request $request): Response
    {
        return UiPage::render('requests');
    }

    public function show(Request $request, string $token): Response
    {
        return UiPage::render('requests.show');
    }
}
