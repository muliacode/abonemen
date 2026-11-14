@props([
    'prefix' => '',
    'bag' => 'default',
])

{{--
    The summary of a form that failed to submit: one link per invalid field, each landing on that field.
    It takes focus when it appears. A field's id is its validation key behind the form's prefix. See docs/accessibility.md.
--}}
@php($messages = $errors->getBag($bag)->messages())

@if ($messages !== [])
    <div
        wire:key="error-summary-{{ md5(json_encode($messages)) }}"
        x-data
        x-init="$nextTick(() => $el.focus())"
        tabindex="-1"
        role="group"
        aria-labelledby="{{ $prefix }}error-summary-heading"
        {{ $attributes->class('rounded-lg border-2 border-red-700 p-4 text-start focus:outline-hidden focus-visible:ring-2 focus-visible:ring-offset-2 dark:border-red-300') }}
        data-error-summary
    >
        <h2 id="{{ $prefix }}error-summary-heading" class="flex items-center gap-2 text-base font-semibold text-red-700 dark:text-red-300">
            <flux:icon name="exclamation-triangle" variant="mini" class="shrink-0" />
            {{ __('There is a problem') }}
        </h2>

        <ul class="mt-2 list-disc ps-5 text-sm">
            @foreach ($messages as $field => $fieldMessages)
                <li>
                    <a href="#{{ $prefix }}{{ $field }}" class="font-medium underline underline-offset-2">{{ $fieldMessages[0] }}</a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
