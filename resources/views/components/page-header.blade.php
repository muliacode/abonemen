@props([
    'title',
    'description' => null,
])

{{--
    The top of a page in the app: its heading, one line saying what the page is for, and the primary action on the right.
    Pass the action in the `actions` slot. See docs/accessibility.md and DESIGN.md.
--}}
<header {{ $attributes->class('mb-8 flex flex-wrap items-center justify-between gap-x-6 gap-y-4') }} data-page-header>
    <div class="min-w-0">
        <x-page-heading>{{ $title }}</x-page-heading>

        @if (filled($description))
            <p class="mt-1 max-w-prose text-muted">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex items-center gap-3">{{ $actions }}</div>
    @endisset
</header>
