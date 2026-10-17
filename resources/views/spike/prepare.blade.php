<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Prepare · Abonemen spike</title>
</head>
<body>
    <main>
        <h1>Prepare the database</h1>
        <p>The check needs its database tables. This runs <code>php artisan migrate</code> on the SQLite file in <code>database/</code>.</p>
        <form method="post" action="{{ route('spike.check.prepare', ['token' => $token]) }}">
            @csrf
            <button type="submit">Prepare the database</button>
        </form>
    </main>
</body>
</html>
