<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Views say what a colour means (text-danger, text-success, text-muted, border-control), because those
 * roles are checked for contrast in both themes (see ColourTokensTest). A raw palette colour for a
 * state or for pale text is not checked by anything, so it is refused here.
 */
class ColourUsageTest extends TestCase
{
    public function test_views_use_colour_roles_instead_of_palette_colours_for_state(): void
    {
        $offences = [];

        foreach ($this->views() as $path => $source) {
            $found = preg_match_all(
                '/(?<![\w:-])(?:!)?(?:dark:)?(?:text|border|bg|ring|fill|stroke)-(?:red|green|lime|emerald|amber|yellow|orange|rose|sky|cyan|blue)-\d+|\bcolor="(?:red|green|lime|amber|yellow|orange|rose)"/',
                $source,
                $matches,
            );

            if ($found) {
                $offences[] = "{$path}: ".implode(', ', array_unique($matches[0]));
            }
        }

        $this->assertSame([], $offences, "Use text-danger, text-success or text-muted (resources/css/app.css) instead:\n".implode("\n", $offences));
    }

    public function test_views_do_not_set_text_in_a_pale_grey_on_the_light_theme(): void
    {
        $offences = [];

        foreach ($this->views() as $path => $source) {
            if (preg_match_all('/(?<![\w:-])text-zinc-(?:300|400)\b/', $source, $matches)) {
                $offences[] = "{$path}: ".implode(', ', array_unique($matches[0]));
            }
        }

        $this->assertSame([], $offences, "Pale grey text is below 4.5:1 on white. Use text-muted:\n".implode("\n", $offences));
    }

    /**
     * Our own views, not Flux's, which are overridden one by one when they fail (see docs/accessibility.md).
     *
     * @return array<string, string>
     */
    private function views(): array
    {
        $root = dirname(__DIR__, 2).'/resources/views';
        $views = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if (str_ends_with($path, '.blade.php') && ! str_contains($path, '/views/flux/')) {
                $views[substr($path, strlen($root) + 1)] = file_get_contents($path);
            }
        }

        $this->assertNotEmpty($views);

        return $views;
    }
}
