<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Recorders;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequestSample
{
    public const array METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    public function __construct(
        public string $route,
        public string $method,
        public int $status,
        public int $milliseconds,
        public ?string $userId = null,
        public string $segment = '',
        public ?string $ip = null,
    ) {}

    public static function capture(Request $request, Response $response, ?string $userId, string $segment): self
    {
        return new self(
            route: self::routeOf($request),
            method: in_array($request->getMethod(), self::METHODS, true) ? $request->getMethod() : 'OTHER',
            status: $response->getStatusCode(),
            milliseconds: self::elapsedMs($request),
            userId: $userId,
            segment: mb_substr($segment, 0, 64),
            ip: $request->ip(),
        );
    }

    public static function statusClass(int $status): string
    {
        return match (true) {
            $status === 429 => '429',
            $status >= 500 => '5xx',
            $status >= 400 => '4xx',
            default => 'ok',
        };
    }

    private static function routeOf(Request $request): string
    {
        $route = $request->route();

        return $route?->getName() ?? $route?->uri() ?? 'unmatched';
    }

    private static function elapsedMs(Request $request): int
    {
        $startedAt = (float) ($request->server('REQUEST_TIME_FLOAT') ?? (defined('LARAVEL_START') ? LARAVEL_START : microtime(true)));

        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }
}
