<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Where a Flux component fails WCAG, its view is overridden in resources/views/flux, and docs/accessibility.md
 * lists the override and the Flux version it was copied from. An override never receives Flux's own fixes,
 * so upgrading Flux fails here until somebody has compared the overrides with the new stubs.
 */
class FluxOverridesTest extends TestCase
{
    public function test_the_overrides_were_compared_with_the_installed_version_of_flux(): void
    {
        $this->assertSame(
            $this->installedFluxVersion(),
            $this->documentedFluxVersion(),
            'Flux was upgraded. Compare each override in resources/views/flux with the new vendor/livewire/flux/stubs, then update the version in docs/accessibility.md.',
        );
    }

    public function test_every_override_is_documented_and_every_documented_override_exists(): void
    {
        $this->assertSame($this->overrides(), $this->documentedOverrides());
    }

    private function installedFluxVersion(): string
    {
        $lock = json_decode(file_get_contents($this->root().'/composer.lock'), true);

        foreach ($lock['packages'] as $package) {
            if ($package['name'] === 'livewire/flux') {
                return ltrim($package['version'], 'v');
            }
        }

        $this->fail('livewire/flux is not installed.');
    }

    private function documentedFluxVersion(): string
    {
        preg_match('/Flux version the overrides are based on:\s*\*\*([\d.]+)\*\*/', $this->documentation(), $matches);

        return $matches[1] ?? '';
    }

    /**
     * @return list<string>
     */
    private function documentedOverrides(): array
    {
        preg_match_all('/^\|\s*`(flux\/[^`]+)`\s*\|/m', $this->documentation(), $matches);

        $paths = $matches[1];
        sort($paths);

        return $paths;
    }

    /**
     * The overridden Flux components, relative to resources/views. Flux icons are our own additions, not overrides.
     *
     * @return list<string>
     */
    private function overrides(): array
    {
        $views = $this->root().'/resources/views';
        $paths = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views.'/flux', RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($views) + 1));

            if (! str_starts_with($path, 'flux/icon/')) {
                $paths[] = $path;
            }
        }

        sort($paths);

        return $paths;
    }

    private function documentation(): string
    {
        $path = $this->root().'/docs/accessibility.md';

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
