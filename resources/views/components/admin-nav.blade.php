{{--
    Moves between the pages of the Instance Admin section. The current page is marked with aria-current and an underline,
    never by colour alone. Hands out links of at least 44px, like every control (docs/accessibility.md).
--}}
<nav aria-label="{{ __('Instance Admin') }}" class="-mt-3 mb-8 flex flex-wrap gap-x-6 border-b border-zinc-300">
    @foreach ([
        'instance.households' => __('Households'),
        'instance.users' => __('Users'),
        'instance.registration' => __('Registration'),
    ] as $route => $label)
        @php($current = request()->routeIs($route))

        <a
            href="{{ route($route) }}"
            wire:navigate
            @if ($current) aria-current="page" @endif
            @class([
                '-mb-px inline-flex min-h-11 items-center border-b-2 px-1 font-medium',
                'border-accent text-accent' => $current,
                'border-transparent text-muted hover:text-ink' => ! $current,
            ])
        >{{ $label }}</a>
    @endforeach
</nav>
