<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Registration surface for the host application:
 *
 *   Ops::auth(fn (Request $request) => $request->user()?->isAdmin());
 */
final class Ops
{
    /** @var (Closure(Request): bool)|null */
    private static ?Closure $authUsing = null;

    /**
     * Decide who may open the Ops dashboard outside the local environment.
     *
     * @param  Closure(Request): bool  $callback
     */
    public static function auth(Closure $callback): void
    {
        self::$authUsing = $callback;
    }

    /**
     * Local is always allowed; otherwise the registered callback decides, falling back to the `viewOps` gate.
     */
    public static function check(Request $request): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        if (self::$authUsing instanceof Closure) {
            return (bool) call_user_func(self::$authUsing, $request);
        }

        return Gate::has('viewOps') && Gate::allows('viewOps');
    }

    /** Reset the registered callback. Test seam. */
    public static function flushAuth(): void
    {
        self::$authUsing = null;
    }
}
