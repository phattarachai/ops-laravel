<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Recorders;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Phattarachai\OpsCore\Histogram\LatencyHistogram;
use Phattarachai\OpsLaravel\Buffer\MinuteBuffer;
use Phattarachai\OpsLaravel\Buffer\MinuteData;
use Phattarachai\OpsLaravel\Enums\OutgoingOutcome;
use Phattarachai\OpsLaravel\Ops;
use Phattarachai\OpsLaravel\Settings\OpsSettings;

/**
 * One recorder for every outbound call made through Laravel's Http client. A failed connection
 * has no transfer stats, so it is counted without a latency.
 */
class OutgoingRecorder
{
    public const string STREAM = 'out';

    public function __construct(
        protected MinuteBuffer $buffer,
        protected OpsSettings $settings,
    ) {}

    public function received(ResponseReceived $event): void
    {
        rescue(function () use ($event): void {
            $status = $event->response->status();
            $seconds = $event->response->transferStats?->getTransferTime();

            $this->write($event->request, $status, OutgoingOutcome::forStatus($status), $seconds === null ? null : (int) round($seconds * 1000));
        }, report: false);
    }

    public function failed(ConnectionFailed $event): void
    {
        rescue(function () use ($event): void {
            $this->write($event->request, 0, OutgoingOutcome::forException($event->exception), null);
        }, report: false);
    }

    protected function write(Request $request, int $status, OutgoingOutcome $outcome, ?int $milliseconds): void
    {
        if (! $this->shouldRecord($request)) {
            return;
        }

        $method = in_array($request->method(), RequestSample::METHODS, true) ? $request->method() : 'OTHER';
        $dims = MinuteData::dims([Ops::outgoingEndpointFor($request), $method, $status, $outcome->value]);
        $increments = [MinuteData::field($dims, 'c') => 1];

        if ($milliseconds !== null) {
            $increments[MinuteData::field($dims, 't')] = 1;
            $increments[MinuteData::field($dims, 'ms')] = $milliseconds;
            $increments[MinuteData::field($dims, 'b'.LatencyHistogram::binMs($milliseconds))] = 1;
        }

        $this->buffer->add(self::STREAM, now(), $increments, $milliseconds === null ? [] : [$dims => $milliseconds]);
    }

    protected function shouldRecord(Request $request): bool
    {
        $host = (string) parse_url($request->url(), PHP_URL_HOST);

        return Ops::recording()
            && $this->settings->bool('recorders.outgoing.enabled')
            && ! in_array($host, $this->settings->list('recorders.outgoing.ignore_hosts'), true);
    }
}
