<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-paper text-ink antialiased">
        <x-skip-link />

        <div class="flex min-h-svh flex-col items-center justify-center px-6 py-10 md:py-16">
            <div class="w-full max-w-md [--mark-knock:var(--color-paper)]">
                @if ($brand ?? true)
                    <x-app-logo href="{{ route('home') }}" wire:navigate class="-ms-2 mb-10 w-fit text-ink [&>svg]:text-accent" />
                @endif

                <main id="main" tabindex="-1" class="flex flex-col gap-6 focus:outline-hidden">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @fluxScripts
    </body>
</html>
