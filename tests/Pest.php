<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Phattarachai\OpsLaravel\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

function rollupAt(string $time): void
{
    test()->travelTo(CarbonImmutable::parse($time));
    test()->artisan('ops:rollup')->assertSuccessful();
}
