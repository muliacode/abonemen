<?php

namespace App\Concerns;

/**
 * Lets a Livewire component confirm what it just did in a message that stays on the page
 * (see the status-message view component), until the User changes something again.
 */
trait ReportsStatus
{
    public ?string $status = null;

    protected function reportStatus(string $message): void
    {
        $this->status = $message;
    }

    public function updated(): void
    {
        $this->status = null;
    }
}
