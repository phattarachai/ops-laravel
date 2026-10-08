<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Rollup;

final readonly class StreamRollups
{
    public function __construct(
        private RequestStreamRollup $requests,
        private OutgoingStreamRollup $outgoing,
        private SignalStreamRollup $signals,
        private QueryStreamRollup $queries,
        private CacheStreamRollup $cache,
    ) {}

    /** @return list<StreamRollup> */
    public function all(): array
    {
        return [$this->requests, $this->outgoing, $this->signals, $this->queries, $this->cache];
    }
}
