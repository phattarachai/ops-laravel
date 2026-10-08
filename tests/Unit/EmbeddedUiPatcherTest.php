<?php

declare(strict_types=1);

use Phattarachai\OpsLaravel\Support\EmbeddedUiPatcher;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/ops-patcher-'.bin2hex(random_bytes(4));
    mkdir($this->dir);
});

afterEach(fn () => array_map(unlink(...), glob($this->dir.'/*')) && rmdir($this->dir));

it('adds the @ops alias into an existing alias block once', function (): void {
    $vite = "{$this->dir}/vite.config.js";
    file_put_contents($vite, "import { defineConfig } from 'vite'\n\nexport default defineConfig({\n    resolve: {\n        alias: {\n            '@': '/resources/js',\n        },\n    },\n});\n");

    expect(EmbeddedUiPatcher::patchViteAlias($vite))->toBeTrue()
        ->and(EmbeddedUiPatcher::patchViteAlias($vite))->toBeTrue();

    $contents = file_get_contents($vite);

    expect(substr_count($contents, "'@ops'"))->toBe(1)
        ->and($contents)->toStartWith("import path from 'node:path';")
        ->toContain(EmbeddedUiPatcher::MODULE_PATH);
});

it('appends a resolve block when the config has none', function (): void {
    $vite = "{$this->dir}/vite.config.js";
    file_put_contents($vite, "export default defineConfig({\n    plugins: [],\n});\n");

    EmbeddedUiPatcher::patchViteAlias($vite);

    expect(file_get_contents($vite))->toContain("resolve: {\n        alias: {\n            '@ops'");
});

it('wires the palette and source right after the tailwind import, idempotently', function (): void {
    $css = "{$this->dir}/app.css";
    file_put_contents($css, "@import 'tailwindcss';\n\n@source '../js/**/*.jsx';\n");

    EmbeddedUiPatcher::patchCss($css);
    EmbeddedUiPatcher::patchCss($css);

    expect(file_get_contents($css))->toBe("@import 'tailwindcss';\n\n".EmbeddedUiPatcher::CSS_IMPORT."\n".EmbeddedUiPatcher::CSS_SOURCE."\n\n@source '../js/**/*.jsx';\n");
});
