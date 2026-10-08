<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Phattarachai\OpsLaravel\Ui\OpsCache;

final class RefreshController
{
    public function __invoke(): RedirectResponse
    {
        OpsCache::bust();

        return back();
    }
}
