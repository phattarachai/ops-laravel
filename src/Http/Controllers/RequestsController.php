<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Response;
use Phattarachai\OpsLaravel\Settings\OpsSettings;
use Phattarachai\OpsLaravel\Ui\Reports\Requests\RequestsReport;
use Phattarachai\OpsLaravel\Ui\Reports\Requests\RequestUsers;
use Phattarachai\OpsLaravel\Ui\Reports\Requests\RouteCatalog;
use Phattarachai\OpsLaravel\Ui\Reports\Requests\RouteDetailReport;
use Phattarachai\OpsLaravel\Ui\TimeFilter;
use Phattarachai\OpsLaravel\Ui\UiPage;

final class RequestsController
{
    public function index(Request $request): Response
    {
        $filter = TimeFilter::fromRequest($request);
        $range = $filter->range();
        $tab = $request->query('tab') === 'users' ? 'users' : 'routes';
        $segments = RequestsReport::segments($range);
        $segment = RequestUsers::segmentFilter($request, $segments);
        $board = RequestsReport::board($range);
        $threshold = RequestsReport::threshold();

        return UiPage::render('requests', [
            ...$this->shared($filter, $threshold),
            'tab' => $tab,
            'summary' => $board['summary'],
            'hot_users' => $this->hotUsers(RequestsReport::hotUsers($range, $threshold), $filter),
            'routes' => $tab === 'routes' ? $this->withUrls($board['routes'], $filter) : [],
            'users' => $tab === 'users' ? RequestsReport::topUsers($range, segment: $segment) : [],
            'user_filters' => ['segment' => $segment],
            'segment_options' => $segments,
            'selected_user' => RequestUsers::selected($request, null, $filter),
        ]);
    }

    public function show(Request $request, string $token): Response
    {
        $route = RouteCatalog::decode($token) ?? abort(404);
        $filter = TimeFilter::fromRequest($request);
        $range = $filter->range();
        $segments = RequestsReport::segments($range);
        $segment = RequestUsers::segmentFilter($request, $segments);
        $summary = collect(RequestsReport::board($range)['routes'])->firstWhere('route', $route);
        $charts = RouteDetailReport::charts($route, $range);

        return UiPage::render('requests.show', [
            ...$this->shared($filter, RequestsReport::threshold()),
            'route' => ['name' => $route, 'methods' => $summary['methods'] ?? null, ...RouteCatalog::describe($route)],
            'back_url' => route('ops.requests', $filter->params()),
            'summary' => $summary,
            'today' => RouteDetailReport::sameTimeLastWeek($route),
            'buckets' => $charts['buckets'],
            'segment_split' => $charts['segments'],
            'histogram' => $charts['histogram'],
            'users' => RequestsReport::topUsers($range, $route, $segment),
            'user_filters' => ['segment' => $segment],
            'segment_options' => $segments,
            'selected_user' => RequestUsers::selected($request, $route, $filter),
        ]);
    }

    /** @return array{time_filter: array<string, mixed>, recording: bool, threshold: int} */
    private function shared(TimeFilter $filter, int $threshold): array
    {
        return [
            'time_filter' => $filter->toProps(RequestsReport::dataUpdatedAt()),
            'recording' => app(OpsSettings::class)->bool('recorders.requests.enabled'),
            'threshold' => $threshold,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $routes
     * @return list<array<string, mixed>>
     */
    private function withUrls(array $routes, TimeFilter $filter): array
    {
        return array_map(fn (array $route): array => [...$route, 'url' => RouteCatalog::url((string) $route['route'], $filter)], $routes);
    }

    /**
     * @param  list<array<string, mixed>>  $hotUsers
     * @return list<array<string, mixed>>
     */
    private function hotUsers(array $hotUsers, TimeFilter $filter): array
    {
        return array_map(fn (array $hot): array => [
            ...$hot,
            'route_url' => RouteCatalog::url((string) $hot['route'], $filter, ['user' => (string) $hot['user_id']]),
        ], $hotUsers);
    }
}
