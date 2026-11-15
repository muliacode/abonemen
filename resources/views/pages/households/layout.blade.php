@props(['household', 'heading' => '', 'subheading' => ''])

{{-- The frame of every page about one Household: its name, the menu between its pages, then the page's own section. --}}
<x-page-header :title="$household->name" :description="__('Its details, its Members and what it sends out')" />

<x-household-nav :household="$household" />

<div class="w-full max-w-lg">
    <flux:heading level="2">{{ $heading }}</flux:heading>
    <flux:subheading>{{ $subheading }}</flux:subheading>

    <div class="mt-5 w-full">
        {{ $slot }}
    </div>
</div>
