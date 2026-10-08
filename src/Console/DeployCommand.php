<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Console;

use Illuminate\Console\Command;
use Phattarachai\OpsLaravel\Samplers\DeployRecorder;

final class DeployCommand extends Command
{
    protected $signature = 'ops:deploy {release? : Release name or SHA (default: detected)} {--description= : Shown on the deploy marker}';

    protected $description = 'Record a deploy marker for the Ops charts';

    public function handle(DeployRecorder $deploys): int
    {
        $release = $this->argument('release') ?? DeployRecorder::currentRelease();

        if (! is_string($release) || $release === '') {
            $this->components->error('No release given and none could be detected.');

            return self::FAILURE;
        }

        $description = $this->option('description');
        $deploys->record($release, is_string($description) ? $description : null);
        $this->components->info("Deploy {$release} recorded.");

        return self::SUCCESS;
    }
}
