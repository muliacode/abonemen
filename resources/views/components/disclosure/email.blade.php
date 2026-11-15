@props(['destination'])

{{--
    What email to a Household sends and where it goes, shown to an Owner before they switch it on (ADR 0007).

    If the data or the recipients below change, raise Connection::Email->disclosureVersion() so that Owners confirm again.
    A change of wording alone does not need a new version.
--}}
<div {{ $attributes->class('flex flex-col gap-4 rounded-lg border-2 border-zinc-300 p-4') }} data-test="disclosure-email">
    <h3 class="text-base font-semibold">{{ __('What email sends') }}</h3>

    <dl class="flex flex-col gap-3">
        <div>
            <dt class="font-medium">{{ __('Invitations') }}</dt>
            <dd class="text-muted">{{ __('Sent to the address an Owner invites. They contain the name of this Household, the name of the person who invited them and a link to join.') }}</dd>
        </div>
        <div>
            <dt class="font-medium">{{ __('Reminders') }}</dt>
            <dd class="text-muted">{{ __('Sent to the Holder of a Subscription and to the Owners. They contain the name of the Subscription, its price and currency, and the date it is about, such as a payment, a cancel-by deadline or the end of a trial.') }}</dd>
        </div>
        <div>
            <dt class="font-medium">{{ __('Where it goes') }}</dt>
            <dd class="text-muted" data-test="disclosure-destination">{{ __('Through the mail server :destination. Email is not encrypted from end to end, so whoever runs that server can read these emails.', ['destination' => $destination]) }}</dd>
        </div>
    </dl>
</div>
