<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Server check · Abonemen spike</title>
    <style>
        body { font: 16px/1.5 system-ui, sans-serif; margin: 2rem auto; max-width: 60rem; padding: 0 1rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #767676; padding: .4rem .6rem; text-align: left; vertical-align: top; }
        .ok { color: #1a7f37; } .warn { color: #8a5a00; } .fail { color: #b3261e; font-weight: 700; }
    </style>
</head>
<body>
    <main>
        <h1>Abonemen shared-hosting check</h1>
        <p>Spike <a href="https://github.com/muliacode/abonemen/issues/15">#15</a>. This page runs in the web server's PHP; run <code>php artisan spike:server-check</code> over SSH too, because cron often uses a different PHP.</p>

        <h2>Server</h2>
        <table>
            <thead><tr><th scope="col">Check</th><th scope="col">Result</th><th scope="col">Detail</th></tr></thead>
            <tbody>
                @foreach ($checks as $check)
                    <tr>
                        <th scope="row">{{ $check['name'] }}</th>
                        <td class="{{ $check['status'] }}">{{ strtoupper($check['status']) }}</td>
                        <td>{{ $check['detail'] }}</td>
                    </tr>
                @endforeach
                <tr>
                    <th scope="row">Document root is public/</th>
                    <td class="{{ $publicIsRoot ? 'ok' : 'fail' }}">{{ $publicIsRoot ? 'OK' : 'FAIL' }}</td>
                    <td>{{ $documentRoot }}</td>
                </tr>
            </tbody>
        </table>
        <p>Also open <code>/.env</code> and <code>/../.env</code> on this site: both must give an error, never the file.</p>

        <h2>Cron-mode queue test</h2>
        <p>{{ $probes['done'] }} of {{ $probes['total'] }} jobs done{{ $probes['last'] ? ', last at '.$probes['last'] : '' }}, after {{ count($runs) }} cron runs.</p>
        @if (count($runs) > 0)
            <table>
                <thead><tr><th scope="col">Run</th><th scope="col">Started</th><th scope="col">Jobs waiting at start</th><th scope="col">PHP</th></tr></thead>
                <tbody>
                    @foreach ($runs as $i => $run)
                        <tr><td>{{ $i + 1 }}</td><td>{{ $run->started_at }}</td><td>{{ $run->pending_at_start }}</td><td>{{ $run->php_version }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <form method="post" action="{{ route('spike.check.queue', ['token' => $token]) }}">
            @csrf
            <button type="submit">Queue 100 jobs of 0.5 s</button>
        </form>
    </main>
</body>
</html>
