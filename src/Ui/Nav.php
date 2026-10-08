<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui;

use Phattarachai\OpsLaravel\Ops;

final class Nav
{
    /** @var list<array{label: string, route: string, icon: string, group: string|null}> */
    private const array PAGES = [
        ['label' => 'Overview', 'route' => 'ops.overview', 'icon' => 'layout-dashboard', 'group' => null],
        ['label' => 'Incoming', 'route' => 'ops.requests', 'icon' => 'arrow-down-left', 'group' => 'Traffic'],
        ['label' => 'Outgoing', 'route' => 'ops.outgoing', 'icon' => 'arrow-up-right', 'group' => 'Traffic'],
        ['label' => 'Jobs', 'route' => 'ops.jobs', 'icon' => 'layers', 'group' => 'Background'],
        ['label' => 'Schedules', 'route' => 'ops.schedules', 'icon' => 'calendar-clock', 'group' => 'Background'],
        ['label' => 'System', 'route' => 'ops.system', 'icon' => 'gauge', 'group' => 'Background'],
        ['label' => 'Incidents', 'route' => 'ops.incidents', 'icon' => 'bell', 'group' => 'Alerts'],
        ['label' => 'Rules', 'route' => 'ops.rules', 'icon' => 'scroll-text', 'group' => 'Alerts'],
        ['label' => 'Settings', 'route' => 'ops.settings', 'icon' => 'wrench', 'group' => 'Alerts'],
    ];

    /** @return list<array{label: string, url: string, icon: string, group: string|null, active: bool, external: bool}> */
    public static function items(): array
    {
        $pages = array_map(fn (array $page): array => [
            'label' => $page['label'],
            'url' => route($page['route']),
            'icon' => $page['icon'],
            'group' => $page['group'],
            'active' => self::isActive($page['route']),
            'external' => false,
        ], self::PAGES);

        return [...$pages, ...Ops::navLinks()];
    }

    private static function isActive(string $route): bool
    {
        return $route === 'ops.overview' ? request()->routeIs($route) : request()->routeIs($route, "{$route}.*");
    }
}
