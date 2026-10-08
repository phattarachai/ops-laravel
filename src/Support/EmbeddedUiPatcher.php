<?php

declare(strict_types=1);

namespace Phattarachai\OpsLaravel\Support;

/**
 * Host build wiring for the dashboard: the `@ops` Vite alias, and two lines in the host's
 * Tailwind entry (`@import` the ops palette, `@source` the package JSX). Every method is
 * idempotent: a second run finds its own marker and does nothing.
 */
final class EmbeddedUiPatcher
{
    public const string ALIAS = '@ops';

    public const string MODULE_PATH = 'vendor/phattarachai/ops-laravel/resources/js/ops/index.js';

    public const string CSS_IMPORT = "@import '../../vendor/phattarachai/ops-laravel/resources/css/ops.css';";

    public const string CSS_SOURCE = "@source '../../vendor/phattarachai/ops-laravel/resources/js/ops';";

    /** @return list<string> */
    public static function viteConfigPaths(string $basePath): array
    {
        return array_values(array_filter([$basePath.'/vite.config.js', $basePath.'/vite.config.ts', $basePath.'/vite.config.mjs'], is_file(...)));
    }

    public static function cssEntryPath(string $basePath): ?string
    {
        foreach (['resources/css/app.css', 'resources/css/inertia.css'] as $candidate) {
            if (is_file("{$basePath}/{$candidate}")) {
                return "{$basePath}/{$candidate}";
            }
        }

        return null;
    }

    public static function pageExtension(string $basePath): string
    {
        return is_file($basePath.'/resources/js/app.tsx') || is_file($basePath.'/tsconfig.json') ? 'tsx' : 'jsx';
    }

    public static function hasAlias(string $path): bool
    {
        return is_file($path) && str_contains((string) file_get_contents($path), "'".self::ALIAS."'");
    }

    public static function patchViteAlias(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $contents = (string) file_get_contents($path);

        if (str_contains($contents, "'".self::ALIAS."'")) {
            return true;
        }

        $entry = "            '".self::ALIAS."': path.resolve(__dirname, '".self::MODULE_PATH."'),";
        $patched = self::insertIntoExistingAlias($contents, $entry) ?? self::appendResolveBlock($contents, $entry);

        if ($patched === null) {
            return false;
        }

        return file_put_contents($path, self::ensureNodePathImport($patched)) !== false;
    }

    public static function hasCss(string $path): bool
    {
        $contents = is_file($path) ? (string) file_get_contents($path) : '';

        return str_contains($contents, 'ops-laravel/resources/css/ops.css') && str_contains($contents, 'ops-laravel/resources/js/ops');
    }

    public static function patchCss(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        if (self::hasCss($path)) {
            return true;
        }

        $contents = (string) file_get_contents($path);
        $lines = self::CSS_IMPORT."\n".self::CSS_SOURCE;
        $tailwind = preg_match("/^@import\s+['\"]tailwindcss['\"][^;\n]*;/m", $contents, $match, PREG_OFFSET_CAPTURE) === 1;
        $end = $tailwind ? $match[0][1] + strlen($match[0][0]) : 0;

        $patched = $tailwind
            ? substr($contents, 0, $end)."\n\n".$lines.substr($contents, $end)
            : $lines."\n\n".$contents;

        return file_put_contents($path, $patched) !== false;
    }

    private static function ensureNodePathImport(string $contents): string
    {
        return str_contains($contents, "from 'node:path'") || str_contains($contents, "from 'path'") ? $contents : "import path from 'node:path';\n".$contents;
    }

    private static function insertIntoExistingAlias(string $contents, string $entry): ?string
    {
        if (preg_match('/alias\s*:\s*\{/', $contents, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);

        return substr($contents, 0, $offset)."\n".$entry.substr($contents, $offset);
    }

    private static function appendResolveBlock(string $contents, string $entry): ?string
    {
        $block = "    resolve: {\n        alias: {\n".$entry."\n        },\n    },";
        $position = strrpos($contents, '});') ?: strrpos($contents, '}');

        return $position === false ? null : substr($contents, 0, $position).$block."\n".substr($contents, $position);
    }
}
