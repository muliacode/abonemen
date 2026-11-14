{{-- The mark and the name, as the link home. The wrapper sets --mark-knock to the colour behind it. --}}
<a {{ $attributes->class('flex items-center gap-2.5 rounded-lg px-2 py-1 text-lg font-semibold') }}>
    <x-app-logo-icon class="size-8 shrink-0" />
    <span>{{ config('app.name', 'Abonemen') }}</span>
</a>
