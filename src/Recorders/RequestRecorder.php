<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Recorders;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Phattarachai\OpsCore\Histogram\LatencyHistogram;
use Phattarachai\OpsLaravel\Buffer\MinuteBuffer;
use Phattarachai\OpsLaravel\Buffer\MinuteData;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Settings\OpsSettings;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after the response is sent (terminable middleware) and writes one pipeline to the minute
 * buffer. Never throws and never resolves a user a guard has not already resolved.
 */
class RequestRecorder
{
    public const string STREAM = 'req';

    public const string USER_STREAM = 'usr';

    public function __construct(
        protected MinuteBuffer $buffer,
        protected OpsSettings $settings,
        protected SignalRecorder $signals,
    ) {}

    public function record(Request $request, Response $response): void
    {
        rescue(function () use ($request, $response): void {
            if (! $this->shouldRecord($request)) {
                return;
            }

            $user = $this->resolvedUser();

            $this->write(RequestSample::capture($request, $response, $user === null ? null : (string) $user->getAuthIdentifier(), Ops::segmentFor($user)));
        }, report: false);
    }

    public function write(RequestSample $sample): void
    {
        $now = now();
        $dims = MinuteData::dims([$sample->route, $sample->method, $sample->status, $sample->segment]);

        $this->buffer->add(self::STREAM, $now, [
            MinuteData::field($dims, 'c') => 1,
            MinuteData::field($dims, 'ms') => $sample->milliseconds,
            MinuteData::field($dims, 'b'.LatencyHistogram::binMs($sample->milliseconds)) => 1,
        ], [$dims => $sample->milliseconds]);

        if ($sample->userId !== null) {
            $userDims = MinuteData::dims([$sample->route, $sample->userId, $sample->segment]);

            $this->buffer->add(self::USER_STREAM, $now, [
                MinuteData::field($userDims, 'c') => 1,
                MinuteData::field($userDims, 'ms') => $sample->milliseconds,
                MinuteData::field($userDims, RequestSample::statusClass($sample->status)) => 1,
            ]);
        }

        $this->recordSecuritySignal($sample);
    }

    protected function recordSecuritySignal(RequestSample $sample): void
    {
        if (! $this->settings->bool('recorders.security.enabled') || ! in_array($sample->status, array_map(intval(...), (array) $this->settings->get('recorders.security.statuses', [])), true)) {
            return;
        }

        $signal = "http.{$sample->status}";
        $meta = ['route' => $sample->route];

        if ($sample->ip !== null) {
            $this->signals->record($signal, "ip:{$sample->ip}", $meta);
        }

        if ($sample->userId !== null) {
            $this->signals->record($signal, "user:{$sample->userId}", $meta);
        }
    }

    protected function shouldRecord(Request $request): bool
    {
        return Ops::recording()
            && $this->settings->bool('recorders.requests.enabled')
            && $request->is(...$this->settings->list('recorders.requests.paths'))
            && ! $request->is(...[...$this->settings->list('recorders.requests.ignore'), trim((string) config('ops.path'), '/').'*']);
    }

    protected function resolvedUser(): ?Authenticatable
    {
        return auth()->hasUser() ? auth()->user() : null;
    }
}
