@props([
    'message' => null,
])

{{--
    A confirmation that stays on the page, in a live region, until the User does something else.
    It is always rendered, so that a message appearing later is read out. See docs/accessibility.md.
--}}
<div role="status" tabindex="-1" {{ $attributes->class('text-sm font-medium text-green-800 focus:outline-hidden dark:text-green-300') }} data-status-message>
    @if (filled($message))
        <flux:icon name="check-circle" variant="mini" class="inline" />
        {{ $message }}
    @endif
</div>
