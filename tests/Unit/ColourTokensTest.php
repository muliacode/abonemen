<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The colour roles of resources/css/app.css meet WCAG 2.2 AA in both themes:
 * 4.5:1 for text (1.4.3) and 3:1 for the edges of controls and the focus ring (1.4.11).
 *
 * Views use these roles (text-danger, border-control) and never a raw palette colour for a state,
 * so that this test is the one place where the contrast of the application is decided.
 */
class ColourTokensTest extends TestCase
{
    private const SURFACES = ['paper', 'surface'];

    /**
     * @return array<string, array{string, string, float}>
     */
    public static function roles(): array
    {
        $roles = [];

        foreach (['light', 'dark'] as $theme) {
            // Flux sets its secondary text in zinc-500 on the light theme and zinc-400 on the dark one.
            $flux = $theme === 'light' ? 'zinc-500' : 'zinc-400';

            foreach (['ink', 'muted', 'accent', 'danger', 'success', $flux] as $text) {
                $roles["{$theme}: {$text} text"] = [$theme, $text, 4.5];
            }

            foreach (['control-border', 'focus'] as $graphic) {
                $roles["{$theme}: {$graphic}"] = [$theme, $graphic, 3.0];
            }
        }

        return $roles;
    }

    #[DataProvider('roles')]
    public function test_a_colour_role_is_readable_on_every_surface(string $theme, string $role, float $minimum): void
    {
        $colours = $this->colours($theme);

        foreach (self::SURFACES as $surface) {
            $this->assertArrayHasKey($role, $colours, "The {$theme} theme defines --color-{$role}.");
            $this->assertArrayHasKey($surface, $colours, "The {$theme} theme defines --color-{$surface}.");

            $ratio = $this->contrast($colours[$role], $colours[$surface]);

            $this->assertGreaterThanOrEqual($minimum, $ratio, sprintf(
                '%s on %s in the %s theme is %.2f:1, below %.1f:1.',
                $role, $surface, $theme, $ratio, $minimum,
            ));
        }
    }

    /**
     * @return array<string, array{string, string, string, float}>
     */
    public static function grounds(): array
    {
        $pairs = [];

        foreach (['light', 'dark'] as $theme) {
            foreach ([
                ['rail-text', 'rail', 4.5],
                ['rail-muted', 'rail', 4.5],
                ['accent-foreground', 'accent', 4.5],
                ['warning', 'warning-surface', 4.5],
                ['warning', 'paper', 4.5],
                ['accent', 'accent-wash', 4.5],
            ] as [$foreground, $background, $minimum]) {
                $pairs["{$theme}: {$foreground} on {$background}"] = [$theme, $foreground, $background, $minimum];
            }
        }

        return $pairs;
    }

    #[DataProvider('grounds')]
    public function test_text_is_readable_on_the_ground_it_sits_on(string $theme, string $foreground, string $background, float $minimum): void
    {
        $colours = $this->colours($theme);

        $this->assertArrayHasKey($foreground, $colours, "The {$theme} theme defines --color-{$foreground}.");
        $this->assertArrayHasKey($background, $colours, "The {$theme} theme defines --color-{$background}.");

        $ratio = $this->contrast($colours[$foreground], $colours[$background]);

        $this->assertGreaterThanOrEqual($minimum, $ratio, sprintf(
            '%s on %s in the %s theme is %.2f:1, below %.1f:1.',
            $foreground, $background, $theme, $ratio, $minimum,
        ));
    }

    /**
     * The signature of the shell is the current page as a tab of paper joined to an ink rail. If the rail and the
     * paper look alike, as they did in dark when both were near-black, the tab has nothing to stand out from.
     */
    #[DataProvider('themes')]
    public function test_the_rail_stands_apart_from_the_paper(string $theme): void
    {
        $colours = $this->colours($theme);

        $ratio = $this->contrast($colours['rail'], $colours['paper']);

        $this->assertGreaterThanOrEqual(2.0, $ratio, sprintf('The rail is %.2f:1 against the paper in the %s theme.', $ratio, $theme));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function themes(): array
    {
        return ['light' => ['light'], 'dark' => ['dark']];
    }

    public function test_white_text_on_a_danger_button_is_readable(): void
    {
        $colours = $this->colours('light');

        foreach (['red-500', 'red-600'] as $background) {
            $this->assertArrayHasKey($background, $colours, "Flux paints danger buttons with {$background}, which app.css pins to a dark enough red.");
            $this->assertGreaterThanOrEqual(4.5, $this->contrast('#ffffff', $colours[$background]), "White on {$background} is below 4.5:1.");
        }
    }

    public function test_the_contrast_formula_matches_known_ratios(): void
    {
        $this->assertEqualsWithDelta(21.0, $this->contrast('#000000', '#ffffff'), 0.01);
        $this->assertEqualsWithDelta(1.0, $this->contrast('#777777', '#777777'), 0.01);
        $this->assertEqualsWithDelta(4.48, $this->contrast('#777777', '#ffffff'), 0.01);
    }

    /**
     * The colours a theme defines: the light ones in @theme, the dark ones layered over them in .dark.
     *
     * @return array<string, string>
     */
    private function colours(string $theme): array
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

        preg_match('/@theme\s*\{(.*?)\n\}/s', $css, $light);
        preg_match('/\.dark\s*\{(.*?)\n\s*\}/s', $css, $dark);

        $colours = $this->hexColours($light[1] ?? '');

        return $theme === 'dark' ? [...$colours, ...$this->hexColours($dark[1] ?? '')] : $colours;
    }

    /**
     * @return array<string, string>
     */
    private function hexColours(string $block): array
    {
        preg_match_all('/--color-([a-z0-9-]+):\s*(#[0-9a-fA-F]{6})\s*;/', $block, $matches, PREG_SET_ORDER);

        return array_column($matches, 2, 1);
    }

    /**
     * The WCAG contrast ratio between two colours.
     */
    private function contrast(string $a, string $b): float
    {
        [$lighter, $darker] = [max($this->luminance($a), $this->luminance($b)), min($this->luminance($a), $this->luminance($b))];

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(
            fn (string $channel) => ($c = hexdec($channel) / 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            str_split(ltrim($hex, '#'), 2),
        );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
