@php
    $mail = app(\App\Support\MailConfiguration::class)->source();
    $tested = app(\App\Support\PlatformSettings::class)->mailTestSucceeded();

    // Only what still needs a look. A real mailer in .env, or saved settings that sent a test email, are done.
    $items = [];

    if ($mail === 'undelivered') {
        $items['email'] = [
            __('Emails are not delivered yet'),
            __('Nobody receives a password reset or a verification link until you set up email.'),
            route('instance.email'),
            __('Set up email'),
        ];
    } elseif ($mail === 'saved' && ! $tested) {
        $items['email'] = [
            __('Send a test email to check your email settings'),
            __('Your settings are saved but have not been tried yet.'),
            route('instance.email'),
            __('Send a test email'),
        ];
    }

    if (! app(\App\Support\InstanceAddress::class)->matches(request())) {
        $items['address'] = [
            __('The address does not match APP_URL'),
            __('Links in emails and the domain of passkeys would point to the wrong place.'),
            route('instance.server'),
            __('See the Server page'),
        ];
    }
@endphp

{{--
    "Get started": what an Instance Admin still has to look at. It lists only what is open, has no dismiss button because
    each item is something real, and is gone when nothing is left. See the Instance Admin section in the README.
--}}
@if ($items !== [])
    <section aria-labelledby="get-started-heading" class="mb-8 max-w-2xl rounded-lg border-2 border-accent p-4" data-test="get-started">
        <h2 id="get-started-heading" class="text-base font-semibold">{{ __('Get started') }}</h2>

        <ul class="mt-3 flex flex-col gap-3">
            @foreach ($items as $key => [$title, $explanation, $href, $action])
                <li data-test="checklist-{{ $key }}">
                    <p class="font-medium">{{ $title }}</p>
                    <p class="text-muted">{{ $explanation }}</p>
                    <p class="mt-1"><flux:link :href="$href" wire:navigate>{{ $action }}</flux:link></p>
                </li>
            @endforeach
        </ul>
    </section>
@endif
