<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main id="main" tabindex="-1" class="focus:outline-hidden">
        <div class="max-w-6xl">
            @if (session()->has('confirmation'))
                <x-status-message class="mb-6" :message="session('confirmation')" />
            @endif

            {{ $slot }}
        </div>
    </flux:main>
</x-layouts::app.sidebar>
