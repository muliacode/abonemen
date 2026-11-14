@props([
    'status',
])

@if (filled($status))
    <x-status-message {{ $attributes }} :message="$status" />
@endif
