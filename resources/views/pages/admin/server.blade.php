<?php

use App\Support\InstanceAddress;
use App\Support\MailConfiguration;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Server')] class extends Component {
    /**
     * The address the admin is visiting, which is what links in emails and the passkey domain should be.
     */
    #[Computed]
    public function visitedAddress(): string
    {
        return app(InstanceAddress::class)->visited(request());
    }

    #[Computed]
    public function addressMatches(): bool
    {
        return app(InstanceAddress::class)->matches(request());
    }

    #[Computed]
    public function database(): string
    {
        $driver = DB::connection()->getDriverName();

        return ['sqlite' => 'SQLite', 'mysql' => 'MySQL', 'mariadb' => 'MariaDB', 'pgsql' => 'PostgreSQL', 'sqlsrv' => 'SQL Server'][$driver] ?? $driver;
    }

    #[Computed]
    public function emailSource(): string
    {
        return app(MailConfiguration::class)->source();
    }
}; ?>

<section class="w-full">
    <x-page-header :title="__('Server')" :description="__('What this instance runs on. These are set in your .env file, not here.')" />

    <x-admin-nav />

    @unless ($this->addressMatches)
        <div class="mb-8 max-w-2xl rounded-lg border-2 border-danger p-4" data-test="address-warning">
            <h2 class="flex items-center gap-2 text-base font-semibold text-danger">
                <flux:icon name="exclamation-triangle" variant="mini" class="shrink-0" />
                {{ __('The address does not match APP_URL') }}
            </h2>
            <p class="mt-2">{{ __('You are visiting :visited, but APP_URL says :configured. Links in emails and the domain of passkeys use APP_URL, so they would point to the wrong place. Change this line in your .env file:', ['visited' => $this->visitedAddress, 'configured' => config('app.url')]) }}</p>
            <p class="mt-2"><code class="font-mono">APP_URL={{ $this->visitedAddress }}</code></p>
        </div>
    @endunless

    <table class="w-full max-w-2xl text-start" data-test="server-table">
        <caption class="sr-only">{{ __('Server settings') }}</caption>
        <tbody>
            <tr class="border-b border-zinc-200">
                <th scope="row" class="w-1/3 py-3 pe-4 text-start align-top font-medium">{{ __('Address') }}</th>
                <td class="py-3">{{ $this->visitedAddress }}</td>
            </tr>
            <tr class="border-b border-zinc-200">
                <th scope="row" class="py-3 pe-4 text-start align-top font-medium">{{ __('Database') }}</th>
                <td class="py-3">
                    {{ $this->database }}
                    <p class="text-muted">{{ __('Change it with the DB_ settings in .env. The README explains how.') }}</p>
                </td>
            </tr>
            <tr class="border-b border-zinc-200">
                <th scope="row" class="py-3 pe-4 text-start align-top font-medium">{{ __('Master key') }}</th>
                <td class="py-3">{{ filled(config('protection.master_key')) ? __('Set') : __('Missing') }}</td>
            </tr>
            <tr class="border-b border-zinc-200">
                <th scope="row" class="py-3 pe-4 text-start align-top font-medium">{{ __('Application key') }}</th>
                <td class="py-3">{{ filled(config('app.key')) ? __('Set') : __('Missing') }}</td>
            </tr>
            <tr class="border-b border-zinc-200">
                <th scope="row" class="py-3 pe-4 text-start align-top font-medium">{{ __('Email') }}</th>
                <td class="py-3">
                    @if ($this->emailSource === 'saved')
                        {{ __('Using the settings saved here') }}
                    @elseif ($this->emailSource === 'env')
                        {{ __('Using the settings from .env') }}
                    @else
                        <span class="font-medium text-danger">{{ __('Emails are not delivered yet') }}</span>
                    @endif
                    <p><flux:link :href="route('instance.email')" wire:navigate>{{ __('Set up email') }}</flux:link></p>
                </td>
            </tr>
            <tr class="border-b border-zinc-200">
                <th scope="row" class="py-3 pe-4 text-start align-top font-medium">{{ __('Queue') }}</th>
                <td class="py-3">{{ config('queue.default') }}</td>
            </tr>
            <tr class="border-b border-zinc-200">
                <th scope="row" class="py-3 pe-4 text-start align-top font-medium">{{ __('Cache') }}</th>
                <td class="py-3">{{ config('cache.default') }}</td>
            </tr>
            <tr class="border-b border-zinc-200">
                <th scope="row" class="py-3 pe-4 text-start align-top font-medium">{{ __('Software') }}</th>
                <td class="py-3">Laravel {{ app()->version() }} · PHP {{ PHP_VERSION }}</td>
            </tr>
        </tbody>
    </table>
</section>
