<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Phattarachai\OpsLaravel\Alerts\DefaultRules;
use Phattarachai\OpsLaravel\Support\EmbeddedUiPatcher;
use Throwable;

final class InstallCommand extends Command
{
    protected $signature = 'ops:install
        {--dry-run : Show what would change without writing anything}
        {--no-rules : Do not seed the default alert rules}';

    protected $description = 'Publish the Ops config and dashboard page, wire Vite + Tailwind, seed default alert rules';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->publishConfig($dryRun);
        $this->publishPage($dryRun);
        $this->patchVite($dryRun);
        $this->patchCss($dryRun);
        $this->seedRules($dryRun);
        $this->nextSteps();

        return self::SUCCESS;
    }

    private function publishConfig(bool $dryRun): void
    {
        $this->step(is_file(config_path('ops.php')), 'config/ops.php already published.', 'Publish config/ops.php', $dryRun, fn () => $this->callSilently('vendor:publish', ['--tag' => 'ops-config']));
    }

    private function publishPage(bool $dryRun): void
    {
        $target = resource_path('js/pages/Ops.'.EmbeddedUiPatcher::pageExtension(base_path()));

        $this->step(is_file($target), basename($target).' already published.', 'Publish resources/js/pages/'.basename($target), $dryRun, function () use ($target): void {
            @mkdir(dirname($target), 0755, true);
            copy(dirname(__DIR__, 2).'/stubs/Ops.jsx', $target);
        });
    }

    private function patchVite(bool $dryRun): void
    {
        $configs = EmbeddedUiPatcher::viteConfigPaths(base_path());

        if ($configs === []) {
            $this->components->warn("No vite.config found — add a resolve.alias entry '@ops' → ".EmbeddedUiPatcher::MODULE_PATH);

            return;
        }

        foreach ($configs as $config) {
            $this->step(EmbeddedUiPatcher::hasAlias($config), basename($config).' already declares @ops.', 'Add the @ops alias to '.basename($config), $dryRun, function () use ($config): void {
                if (! EmbeddedUiPatcher::patchViteAlias($config)) {
                    $this->components->warn('Could not patch '.basename($config)." — add '@ops' → ".EmbeddedUiPatcher::MODULE_PATH.' to resolve.alias.');
                }
            });
        }
    }

    private function patchCss(bool $dryRun): void
    {
        $css = EmbeddedUiPatcher::cssEntryPath(base_path());

        if ($css === null) {
            $this->components->warn('No resources/css/app.css found — add these lines to your Tailwind entry:');
            $this->line(EmbeddedUiPatcher::CSS_IMPORT."\n".EmbeddedUiPatcher::CSS_SOURCE);

            return;
        }

        $this->step(EmbeddedUiPatcher::hasCss($css), basename($css).' already imports the ops palette.', 'Import the ops palette and @source the dashboard in '.basename($css), $dryRun, fn () => EmbeddedUiPatcher::patchCss($css));
    }

    private function seedRules(bool $dryRun): void
    {
        if ($this->option('no-rules')) {
            return;
        }

        $ready = (bool) rescue(fn (): bool => Schema::connection(config('ops.connection'))->hasTable('ops_rules'), false, report: false);

        if (! $ready) {
            $this->components->info('Run `php artisan migrate`, then `php artisan ops:install` again to seed the default alert rules.');

            return;
        }

        $this->step(false, '', 'Seed the default alert rules (production only, editable on the Rules page)', $dryRun, function (): void {
            try {
                $this->components->info(DefaultRules::seed().' default rules added.');
            } catch (Throwable $exception) {
                $this->components->warn("Could not seed rules: {$exception->getMessage()}");
            }
        });
    }

    private function step(bool $done, string $doneMessage, string $action, bool $dryRun, callable $apply): void
    {
        if ($done) {
            $this->components->twoColumnDetail($doneMessage, '<fg=gray>skipped</>');

            return;
        }

        if ($dryRun) {
            $this->components->twoColumnDetail("Would: {$action}", '<fg=yellow>dry run</>');

            return;
        }

        $apply();
        $this->components->twoColumnDetail($action, '<fg=green>done</>');
    }

    private function nextSteps(): void
    {
        $this->newLine();
        $this->line('  Next:');
        $this->line('  1. Grant access outside local: <fg=cyan>Ops::auth(fn ($request) => $request->user()?->isAdmin())</> or a <fg=cyan>viewOps</> gate.');
        $this->line('  2. Rebuild assets (<fg=cyan>npm run build</>), then open /'.trim((string) config('ops.path'), '/').'/settings to set mail recipients.');
        $this->line('  3. The buffer needs Redis 6.2+; ops:rollup, ops:alerts and ops:prune are already on the scheduler.');
    }
}
