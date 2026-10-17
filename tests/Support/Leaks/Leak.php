<?php

namespace Tests\Support\Leaks;

/**
 * A canary found on disk.
 */
final readonly class Leak
{
    public function __construct(
        public string $field,
        public string $form,
        public string $location,
    ) {}

    public function __toString(): string
    {
        return "{$this->field} ({$this->form}) in {$this->location}";
    }
}
