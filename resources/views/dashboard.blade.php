<x-layouts::app :title="__('Dashboard')">
    <x-page-header :title="__('Dashboard')" />

    {{-- Four surfaces wait here for the real dashboard (#16). --}}
    <div class="grid gap-5 md:grid-cols-3">
        <div class="h-44 rounded-xl border border-zinc-300 bg-surface"></div>
        <div class="h-44 rounded-xl border border-zinc-300 bg-surface"></div>
        <div class="h-44 rounded-xl border border-zinc-300 bg-surface"></div>
    </div>

    <div class="mt-5 h-96 rounded-xl border border-zinc-300 bg-surface"></div>
</x-layouts::app>
