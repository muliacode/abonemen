<?php

namespace Tests\Feature\Accessibility;

use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RendersPages;
use Tests\TestCase;

class PageStructureTest extends TestCase
{
    use RefreshDatabase;
    use RendersPages;

    #[DataProvider('pages')]
    public function test_every_page_starts_with_a_skip_link_to_its_main_landmark(string $page): void
    {
        $xpath = $this->render($page);

        $firstFocusable = $xpath->query('(//body//*[self::a[@href] or self::button or self::input or self::select or self::textarea])[1]')->item(0);

        $this->assertInstanceOf(DOMElement::class, $firstFocusable);
        $this->assertSame('#main', $firstFocusable->getAttribute('href'), 'The first focusable element is the skip link.');

        $main = $xpath->query('//*[@id="main"]');

        $this->assertCount(1, $main);
        $this->assertSame('main', $main->item(0)->nodeName, 'The skip link targets the <main> landmark.');
    }

    #[DataProvider('pages')]
    public function test_every_page_has_exactly_one_top_level_heading(string $page): void
    {
        $headings = $this->render($page)->query('//h1');

        $this->assertCount(1, $headings);
        $this->assertNotSame('', trim($headings->item(0)->textContent), 'The heading says what the page is.');
    }

    #[DataProvider('pages')]
    public function test_headings_never_skip_a_level(string $page): void
    {
        $previous = 0;

        foreach ($this->render($page)->query('//h1|//h2|//h3|//h4|//h5|//h6') as $heading) {
            $level = (int) substr($heading->nodeName, 1);

            $this->assertLessThanOrEqual($previous + 1, $level, "An <h{$level}> follows an <h{$previous}>.");

            $previous = $level;
        }
    }

    #[DataProvider('pages')]
    public function test_every_navigation_landmark_has_a_name(string $page): void
    {
        foreach ($this->render($page)->query('//nav') as $nav) {
            $this->assertTrue(
                $nav->hasAttribute('aria-label') || $nav->hasAttribute('aria-labelledby'),
                'A <nav> without a name cannot be told apart from the others.',
            );
        }
    }

    #[DataProvider('pages')]
    public function test_the_page_does_not_force_a_theme(string $page): void
    {
        $html = $this->render($page)->query('/html')->item(0);

        $this->assertStringNotContainsString('dark', $html->getAttribute('class'));
        $this->assertNotSame('', $html->getAttribute('lang'));
    }

    #[DataProvider('settingsPages')]
    public function test_a_settings_page_titles_its_section_with_a_visible_heading(string $page): void
    {
        $headings = $this->render($page)->query('//h2');

        $this->assertGreaterThan(0, $headings->length, 'The section has a heading under the page heading.');

        foreach ($headings as $heading) {
            $this->assertStringNotContainsString('sr-only', $heading->getAttribute('class'), 'A heading is for everyone, not only for screen readers.');
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function settingsPages(): array
    {
        return array_intersect_key(self::pages(), array_flip(['profile', 'security', 'appearance', 'language', 'households', 'household settings']));
    }

    #[DataProvider('pages')]
    public function test_every_dialog_is_named_by_its_visible_heading(string $page): void
    {
        $page = $this->render($page);

        foreach ($page->query('//dialog') as $dialog) {
            $labelledBy = $dialog->getAttribute('aria-labelledby');

            $this->assertNotSame('', $labelledBy, 'A dialog says what it is for.');

            $heading = $page->query('//*[@id="'.$labelledBy.'"]')->item(0);

            $this->assertNotNull($heading, 'The dialog is named by an element that exists.');
            $this->assertNotSame('', trim($heading->textContent));
            $this->assertNotNull($page->query('ancestor::dialog', $heading)->item(0), 'The name comes from inside the dialog.');
        }
    }

    public function test_the_current_page_is_marked_in_the_sidebar(): void
    {
        $current = $this->render('dashboard')->query('//nav//a[@aria-current="page"]');

        $this->assertCount(1, $current);
    }
}
