<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Phattarachai\OpsCore\Alerts\Contracts\IncidentChannel;
use Phattarachai\OpsCore\Alerts\ProbeLevel;
use Phattarachai\OpsLaravel\Alerts\ChannelRegistry;
use Phattarachai\OpsLaravel\Models\ProbeState;
use Phattarachai\OpsLaravel\Recorders\SignalRecorder;

/**
 * The host's registration surface, e.g. in AppServiceProvider::boot():
 *
 *   Ops::auth(fn (Request $request) => $request->user()?->isAdmin());
 *   Ops::segmentUsing(fn (User $user) => $user->role->value);
 *   Ops::signal('cross_tenant', key: "user:{$user->id}", meta: ['tenant' => $slug]);
 */
final class Ops
{
    /** @var (Closure(Request): bool)|null */
    private static ?Closure $authUsing = null;

    /** @var (Closure(Authenticatable): string)|null */
    private static ?Closure $segmentUsing = null;

    /** @var (Closure(ClientRequest): ?string)|null */
    private static ?Closure $outgoingEndpointUsing = null;

    private static bool $paused = false;

    /** @var list<array{label: string, url: string, icon: string, group: string|null, active: bool, external: bool}> */
    private static array $navLinks = [];

    /** @param  Closure(Request): bool  $callback */
    public static function auth(Closure $callback): void
    {
        self::$authUsing = $callback;
    }

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

    /** @param  Closure(Authenticatable): string  $callback */
    public static function segmentUsing(Closure $callback): void
    {
        self::$segmentUsing = $callback;
    }

    public static function segmentFor(?Authenticatable $user): string
    {
        return match (true) {
            ! $user instanceof Authenticatable => 'guest',
            self::$segmentUsing instanceof Closure => (string) call_user_func(self::$segmentUsing, $user),
            default => 'user',
        };
    }

    /** @param  Closure(ClientRequest): ?string  $callback */
    public static function outgoingEndpointUsing(Closure $callback): void
    {
        self::$outgoingEndpointUsing = $callback;
    }

    public static function outgoingEndpointFor(ClientRequest $request): string
    {
        $endpoint = self::$outgoingEndpointUsing instanceof Closure ? call_user_func(self::$outgoingEndpointUsing, $request) : null;

        return mb_substr((string) ($endpoint ?? parse_url($request->url(), PHP_URL_HOST) ?? 'unknown'), 0, 255);
    }

    /** @param  array<string, scalar|null>  $meta */
    public static function signal(string $signal, string $key = '', array $meta = [], int $count = 1): void
    {
        app(SignalRecorder::class)->record($signal, $key, $meta, $count);
    }

    public static function probe(string $signal, string $key, ProbeLevel $level, ?float $value = null, ?string $message = null): void
    {
        rescue(fn () => ProbeState::query()->updateOrCreate(
            ['signal' => $signal, 'key' => $key],
            ['level' => $level, 'value' => $value, 'message' => $message === null ? null : mb_substr($message, 0, 500), 'observed_at' => now()],
        ), report: false);
    }

    /** @param  Closure(): IncidentChannel  $factory */
    public static function channel(string $name, Closure $factory): void
    {
        app(ChannelRegistry::class)->register($name, $factory);
    }

    /**
     * Extra sidebar entries, e.g. the host's own monitoring pages or Horizon.
     *
     * @param  list<array{label: string, url: string, icon?: string, group?: string|null, external?: bool}>|null  $links
     * @return list<array{label: string, url: string, icon: string, group: string|null, active: bool, external: bool}>
     */
    public static function navLinks(?array $links = null): array
    {
        if ($links !== null) {
            self::$navLinks = array_map(fn (array $link): array => [
                'label' => $link['label'],
                'url' => $link['url'],
                'icon' => $link['icon'] ?? 'link-2',
                'group' => $link['group'] ?? null,
                'active' => false,
                'external' => $link['external'] ?? true,
            ], $links);
        }

        return array_map(fn (array $link): array => [...$link, 'active' => ! $link['external'] && str_starts_with(request()->url(), $link['url'])], self::$navLinks);
    }

    public static function recording(): bool
    {
        return ! self::$paused && (bool) config('ops.enabled', true);
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function withoutRecording(Closure $callback): mixed
    {
        $previous = self::$paused;
        self::$paused = true;

        try {
            return $callback();
        } finally {
            self::$paused = $previous;
        }
    }

    public static function flush(): void
    {
        self::$authUsing = null;
        self::$segmentUsing = null;
        self::$outgoingEndpointUsing = null;
        self::$paused = false;
        self::$navLinks = [];
    }
}
