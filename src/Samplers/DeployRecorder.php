<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Samplers;

use Phattarachai\OpsLaravel\Models\Deploy;

/**
 * Notices a new release on the first rollup after a deploy: `OPS_RELEASE`, else a REVISION
 * file, else the git HEAD commit. `ops:deploy` records one explicitly.
 */
final class DeployRecorder
{
    public static function currentRelease(): ?string
    {
        $configured = config('ops.release');

        if (is_string($configured) && $configured !== '') {
            return mb_substr($configured, 0, 100);
        }

        return self::revisionFile() ?? self::gitHead();
    }

    public function detect(): ?Deploy
    {
        $release = self::currentRelease();

        if ($release === null || Deploy::query()->latest('deployed_at')->latest('id')->value('release') === $release) {
            return null;
        }

        return $this->record($release);
    }

    public function record(string $release, ?string $description = null): Deploy
    {
        return Deploy::query()->create(['release' => mb_substr($release, 0, 100), 'description' => $description, 'deployed_at' => now()]);
    }

    private static function revisionFile(): ?string
    {
        $revision = @file_get_contents(base_path('REVISION'));

        return is_string($revision) && trim($revision) !== '' ? mb_substr(trim($revision), 0, 100) : null;
    }

    private static function gitHead(): ?string
    {
        $gitDir = self::gitDir();
        $head = $gitDir === null ? false : @file_get_contents("{$gitDir}/HEAD");

        if (! is_string($head)) {
            return null;
        }

        $head = trim($head);

        if (! str_starts_with($head, 'ref: ')) {
            return mb_substr($head, 0, 12);
        }

        $common = @file_get_contents("{$gitDir}/commondir");
        $roots = array_filter([$gitDir, is_string($common) ? $gitDir.'/'.trim($common) : null]);

        foreach ($roots as $root) {
            $ref = @file_get_contents($root.'/'.substr($head, 5));

            if (is_string($ref)) {
                return mb_substr(trim($ref), 0, 12);
            }
        }

        return null;
    }

    private static function gitDir(): ?string
    {
        $path = base_path('.git');

        if (is_dir($path)) {
            return $path;
        }

        $pointer = @file_get_contents($path);

        return is_string($pointer) && str_starts_with($pointer, 'gitdir: ') ? trim(substr($pointer, 8)) : null;
    }
}
