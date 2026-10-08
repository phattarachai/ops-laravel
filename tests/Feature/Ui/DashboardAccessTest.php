<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;
use Phattarachai\OpsLaravel\Ops;

it('renders every screen through the one host page with the shared ops prop', function (string $route, string $view): void {
    Ops::auth(fn (Request $request): bool => true);

    $this->get(route($route))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Ops')
            ->where('view', $view)
            ->has('ops.nav', 8)
            ->where('ops.locale', 'en'));
})->with([
    ['ops.overview', 'overview'],
    ['ops.requests', 'requests'],
    ['ops.outgoing', 'outgoing'],
    ['ops.jobs', 'jobs'],
    ['ops.schedules', 'schedules'],
    ['ops.incidents', 'incidents'],
    ['ops.rules', 'rules'],
    ['ops.settings', 'settings'],
]);

it('refuses the dashboard outside local without authorization', function (): void {
    $this->app['env'] = 'production';

    $this->get(route('ops.overview'))->assertForbidden();
});
