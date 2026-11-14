@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-1">
    <x-page-heading>{{ $title }}</x-page-heading>
    <p class="text-muted">{{ $description }}</p>
</div>
