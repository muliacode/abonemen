<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main id="main" tabindex="-1" class="focus:outline-hidden">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
