<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Requests;

use Illuminate\Support\Str;
use Phattarachai\OpsLaravel\Models\RequestRollup;
use Phattarachai\OpsLaravel\Ui\Format;
use Phattarachai\OpsLaravel\Ui\TimeFilter;

/** Labels for a recorded route: its URI from the host's router, a resource group, the noise flag. */
final class RouteCatalog
{
    public const string UNMATCHED = 'unmatched';

    /** @return array{uri: ?string, group: string, noise: bool} */
    public static function describe(string $route): array
    {
        return [
            'uri' => self::uri($route),
            'group' => self::group($route),
            'noise' => $route === self::UNMATCHED,
        ];
    }

    public static function uri(string $route): ?string
    {
        $uri = str_contains($route, '/') ? $route : app('router')->getRoutes()->getByName($route)?->uri();

        return $uri === null ? null : '/'.ltrim($uri, '/');
    }

    public static function token(string $route): string
    {
        return Format::base64url($route);
    }

    public static function decode(string $token): ?string
    {
        $route = Format::fromBase64url($token);

        if ($route === null || $route === '') {
            return null;
        }

        return RequestRollup::query()->where('grain', 'hour')->where('route', $route)->exists() ? $route : null;
    }

    /** @param  array<string, string>  $query */
    public static function url(string $route, TimeFilter $filter, array $query = []): string
    {
        return route('ops.requests.show', ['token' => self::token($route), ...$query, ...$filter->params()]);
    }

    private static function group(string $route): string
    {
        if (str_contains($route, '/')) {
            return Str::of($route)->ltrim('/')->after('api/')->before('/')->value();
        }

        return Str::before($route, '.');
    }
}
