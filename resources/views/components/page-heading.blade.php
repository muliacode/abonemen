{{-- The one <h1> of a page. Focus moves here after a wire:navigate page change (see resources/js/app.js). --}}
<h1 tabindex="-1" {{ $attributes->class('text-[1.75rem] leading-tight font-semibold text-ink focus:outline-hidden') }} data-page-heading>
    {{ $slot }}
</h1>
