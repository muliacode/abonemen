{{--
    The Abonemen mark: a house with a renewal arrow. The house takes the text colour; the arrow is cut out of it in
    --mark-knock, which the parent sets to the colour behind the mark (the rail, the paper).
--}}
<svg viewBox="0 0 32 32" fill="none" aria-hidden="true" {{ $attributes }}>
    <path fill="currentColor" d="M16 3.2 4.6 12.6c-.4.3-.6.8-.6 1.3V26a3 3 0 0 0 3 3h18a3 3 0 0 0 3-3V13.9c0-.5-.2-1-.6-1.3L16 3.2Z" />
    <path stroke="var(--mark-knock, var(--color-paper))" stroke-width="2.2" d="M15.1 24.62A5.2 5.2 0 1 1 21.12 20.4" />
    <path fill="var(--mark-knock, var(--color-paper))" d="m20.74 22.77 2.58-2.18-4.34-.78Z" />
</svg>
