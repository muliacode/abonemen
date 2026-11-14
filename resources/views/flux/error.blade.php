@blaze

{{-- Overrides Flux: the message has an id the field can refer to, is no longer an alert of its own (the error summary announces the problem) and is dark enough to read. See docs/accessibility.md. --}}

@props([
    'icon' => 'exclamation-triangle',
    'bag' => 'default',
    'message' => null,
    'deep' => true,
    'nested' => true,
    'name' => null,
])

@php
$errorBag = $errors->getBag($bag);
$message ??= $name ? $errorBag->first($name) : null;

// Backwards compatibility...
if ($nested === false) {
    $deep = false;
}

if ($name && (is_null($message) || $message === '') && filter_var($deep, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false) {
    $message = $errorBag->first($name . '.*');
}

$classes = Flux::classes('mt-3 text-sm font-medium text-danger')
    ->add($message ? '' : 'hidden');
@endphp

<div {{ $attributes->class($classes)->merge(['id' => $name ? 'error-'.$name : null]) }} data-flux-error>
    <?php if ($message) : ?>
        <?php if ($icon) : ?>
            <flux:icon :name="$icon" variant="mini" class="inline" />
        <?php endif; ?>

        {{ $message }}
    <?php endif; ?>
</div>
