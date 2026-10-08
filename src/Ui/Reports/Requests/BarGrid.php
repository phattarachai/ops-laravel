<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Ui\Reports\Requests;

use Carbon\CarbonImmutable;

/** Fixed-width bars from an origin: which bar a rollup bucket falls in. */
final readonly class BarGrid
{
    public function __construct(
        public CarbonImmutable $origin,
        public int $step,
        public int $bars,
    ) {}

    public function index(CarbonImmutable $at): ?int
    {
        $index = (int) floor(($at->getTimestamp() - $this->origin->getTimestamp()) / ($this->step * 60));

        return $index >= 0 && $index < $this->bars ? $index : null;
    }
}
