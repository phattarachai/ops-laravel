<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;

final class RecordedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public bool $fail = false) {}

    public function handle(): void
    {
        if ($this->fail) {
            throw new RuntimeException('Upstream timed out after 180001 ms');
        }
    }
}
