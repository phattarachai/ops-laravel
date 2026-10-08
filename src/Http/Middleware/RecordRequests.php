<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Phattarachai\OpsLaravel\Recorders\RequestRecorder;
use Phattarachai\OpsLaravel\Support\OpsContext;
use Symfony\Component\HttpFoundation\Response;

final readonly class RecordRequests
{
    public function __construct(private RequestRecorder $recorder) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        OpsContext::stampOrigin($this->originFor($request));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->recorder->record($request, $response);
    }

    private function originFor(Request $request): string
    {
        foreach ((array) config('ops.origins', []) as $pattern => $origin) {
            if ($request->is((string) $pattern)) {
                return (string) $origin;
            }
        }

        return 'web';
    }
}
