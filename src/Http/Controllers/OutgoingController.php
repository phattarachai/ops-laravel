<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Response;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\OpsCache;
use Phattarachai\OpsLaravel\Ui\Reports\Outgoing\OutgoingBoard;
use Phattarachai\OpsLaravel\Ui\Reports\Outgoing\OutgoingEndpointReport;
use Phattarachai\OpsLaravel\Ui\Reports\Outgoing\OutgoingRows;
use Phattarachai\OpsLaravel\Ui\TimeFilter;
use Phattarachai\OpsLaravel\Ui\UiPage;

final class OutgoingController
{
    public function index(Request $request): Response
    {
        $filter = TimeFilter::fromRequest($request);
        $endpoints = new OutgoingBoard($filter)->rows();

        return UiPage::render('outgoing', [
            'time_filter' => $filter->toProps($this->asOf()),
            'summary' => OutgoingBoard::summary($endpoints),
            'endpoints' => $endpoints,
        ]);
    }

    public function show(Request $request, string $token): Response
    {
        $endpoint = $this->endpoint($token);
        $filter = TimeFilter::fromRequest($request);
        $report = new OutgoingEndpointReport($endpoint, $filter->range());

        return UiPage::render('outgoing.show', [
            'time_filter' => $filter->toProps($this->asOf()),
            'endpoint' => new OutgoingBoard($filter)->row($endpoint),
            ...$report->window(),
            'hour_of_day' => $report->hourOfDay(),
            'back_url' => route('ops.outgoing', $filter->params()),
        ]);
    }

    private function endpoint(string $token): string
    {
        $endpoint = Format::fromBase64url($token);

        if ($endpoint === null || ! $this->isKnown($endpoint)) {
            abort(404);
        }

        return $endpoint;
    }

    private function isKnown(string $endpoint): bool
    {
        return OpsCache::remember('outgoing:exists:'.md5($endpoint), fn (): bool => OutgoingRows::exists($endpoint));
    }

    private function asOf(): ?CarbonImmutable
    {
        $latest = OpsCache::remember('outgoing:as-of', fn (): ?string => OutgoingRows::latestBucket()?->toIso8601String());

        return $latest === null ? null : CarbonImmutable::parse($latest);
    }
}
