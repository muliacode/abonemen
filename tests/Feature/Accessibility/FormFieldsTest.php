<?php

namespace Tests\Feature\Accessibility;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RendersPages;
use Tests\TestCase;

class FormFieldsTest extends TestCase
{
    use RefreshDatabase;
    use RendersPages;

    #[DataProvider('pages')]
    public function test_every_form_control_has_a_visible_label(string $page): void
    {
        foreach ($this->render($page)->query('//*[@data-flux-control]') as $control) {
            $field = $this->closest($control, 'ui-field');

            $this->assertNotNull($field, 'A form control sits inside a field, so that its label and messages belong to it.');

            $labels = (new \DOMXPath($control->ownerDocument))->query('./ui-label', $field);

            $this->assertCount(1, $labels, 'A field has one label.');
            $this->assertNotSame('', trim($labels->item(0)->textContent), 'The label says what to enter.');
            $this->assertStringNotContainsString('sr-only', $labels->item(0)->getAttribute('class'), 'The label is visible.');
        }
    }

    #[DataProvider('pages')]
    public function test_no_form_control_uses_a_placeholder_as_its_label(string $page): void
    {
        $this->assertCount(
            0,
            $this->render($page)->query('//*[@data-flux-control][@placeholder]'),
            'A placeholder vanishes on typing and is read inconsistently; a format hint is help text.',
        );
    }

    private function closest(\DOMElement $element, string $tag): ?\DOMElement
    {
        for ($node = $element->parentNode; $node instanceof \DOMElement; $node = $node->parentNode) {
            if ($node->nodeName === $tag) {
                return $node;
            }
        }

        return null;
    }
}
