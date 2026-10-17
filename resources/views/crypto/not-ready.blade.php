@php($invalid = $status === \App\Support\Crypto\MasterKeyStatus::Invalid)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $invalid ? __('The master key cannot be used') : __('Abonemen is not set up yet') }} – {{ config('app.name', 'Abonemen') }}</title>
        <style>
            body { font-family: system-ui, sans-serif; line-height: 1.5; max-width: 40rem; margin: 3rem auto; padding: 0 1rem; color: #1a1a1a; background: #fff; }
            code { font-size: 0.95em; }
            @media (prefers-color-scheme: dark) { body { color: #f0f0f0; background: #121212; } }
        </style>
    </head>
    <body>
        <main>
            @if ($invalid)
                <h1>{{ __('The master key cannot be used') }}</h1>
                <p>{{ __('A master key is configured, but it cannot be read or has the wrong form. The server log says why.') }}</p>
            @else
                <h1>{{ __('Abonemen is not set up yet') }}</h1>
                <p>{{ __('No master key is configured, so Abonemen cannot read or store any data.') }}</p>
            @endif
            <p>{!! __('Set :file to the path of the key file (preferred), or :variable to the key itself, then reload this page.', ['file' => '<code>ABONEMEN_MASTER_KEY_FILE</code>', 'variable' => '<code>ABONEMEN_MASTER_KEY</code>']) !!}</p>
        </main>
    </body>
</html>
