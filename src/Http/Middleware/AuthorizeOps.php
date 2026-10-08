<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Phattarachai\OpsLaravel\Ops;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeOps
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Ops::check($request), 403);

        return $next($request);
    }
}
