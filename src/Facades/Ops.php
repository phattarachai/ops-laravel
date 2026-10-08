<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Facades;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Phattarachai\OpsLaravel\Ops as OpsManager;

/**
 * @method static void auth(Closure $callback)
 * @method static bool check(Request $request)
 * @method static void flushAuth()
 *
 * @see OpsManager
 */
final class Ops extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OpsManager::class;
    }
}
