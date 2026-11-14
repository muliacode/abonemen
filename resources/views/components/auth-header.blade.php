@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <x-page-heading>{{ $title }}</x-page-heading>
    <flux:subheading>{{ $description }}</flux:subheading>
</div>
