<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Response;
use Phattarachai\OpsLaravel\Ui\Reports\Overview\OverviewReport;
use Phattarachai\OpsLaravel\Ui\TimeFilter;
use Phattarachai\OpsLaravel\Ui\UiPage;

final class OverviewController
{
    public function index(Request $request): Response
    {
        $filter = TimeFilter::fromRequest($request);
        $report = new OverviewReport($filter);

        return UiPage::render('overview', [
            'time_filter' => $filter->toProps($report->dataUpdatedAt()),
            ...$report->props(),
        ]);
    }
}
