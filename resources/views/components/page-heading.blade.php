{{-- The one <h1> of a page. Focus moves here after a wire:navigate page change (see resources/js/app.js). --}}
<flux:heading size="xl" level="1" tabindex="-1" {{ $attributes->class('focus:outline-hidden') }} data-page-heading>
    {{ $slot }}
</flux:heading>
