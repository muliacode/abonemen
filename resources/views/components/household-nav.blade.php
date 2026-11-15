@props(['household'])

{{--
    Moves between the pages of one Household: its details, its Members and its connections. The current page is marked
    with aria-current and an underline, never by colour alone. Links are at least 44px, like every control
    (docs/accessibility.md). Same shape as the Instance Admin menu, so the two read alike.
--}}
<nav aria-label="{{ __('Household pages') }}" class="-mt-3 mb-8 flex flex-wrap gap-x-6 border-b border-zinc-300" data-test="household-nav">
    @foreach (array_filter([
        'households.edit' => ['label' => __('Details'), 'test' => 'household-details-link'],
        'households.members' => ['label' => __('Members'), 'test' => 'household-members-link'],
        'households.connections' => Gate::allows('update', $household) ? ['label' => __('Connections'), 'test' => 'household-connections-link'] : null,
    ]) as $route => $item)
        @php($current = request()->routeIs($route))

        <a
            href="{{ route($route, $household) }}"
            wire:navigate
            @if ($current) aria-current="page" @endif
            data-test="{{ $item['test'] }}"
            @class([
                '-mb-px inline-flex min-h-11 items-center border-b-2 px-1 font-medium',
                'border-accent text-accent' => $current,
                'border-transparent text-muted hover:text-ink' => ! $current,
            ])
        >{{ $item['label'] }}</a>
    @endforeach
</nav>
