<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Response;
use Phattarachai\OpsLaravel\Ui\Reports\System\SystemReport;
use Phattarachai\OpsLaravel\Ui\TimeFilter;
use Phattarachai\OpsLaravel\Ui\UiPage;

final class SystemController
{
    public function __invoke(Request $request): Response
    {
        $filter = TimeFilter::fromRequest($request);
        $report = new SystemReport($filter->range());

        return UiPage::render('system', [
            'time_filter' => $filter->toProps($report->dataUpdatedAt()),
            ...$report->props(),
        ]);
    }
}
