@props([
    'status',
])

<x-status-message {{ $attributes }} :message="$status" />