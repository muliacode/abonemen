import { execFileSync } from 'node:child_process';

/**
 * A page of the app as the accessibility suite visits it.
 */
export interface AppRoute {
    /** Route name, or the URI when the route has none. */
    name: string;
    /** Path to visit, with parameters filled in. */
    path: string;
}

interface ArtisanRoute {
    uri: string;
    name: string | null;
    method: string;
    middleware: string[];
}

/**
 * Routes that are not pages, with the reason. Matched against the route name,
 * or the URI when the route has no name.
 */
const excluded: Record<string, string> = {
    up: 'health check, plain text',
    'storage.local': 'file downloads',
};

/** URI prefixes that belong to packages, not to Abonemen. */
const excludedPrefixes = ['_inertia/', '_ignition/'];

/**
 * Route names of JSON endpoints, which are not pages. Visiting them would also
 * use up their rate limits for the tests that need them.
 */
const excludedJsonRoutes = [
    /^passkey\./,
    /^two-factor\.passkey\./,
    /^two-factor\.(qr-code|secret-key|recovery-codes)$/,
    /^password\.confirmation$/,
];

/**
 * Example parameters for routes that need them, by route name. A page route
 * with parameters fails the suite until it has an entry here, so no page is
 * skipped by accident.
 */
export const routeParameters: Record<string, Record<string, string>> = {};

/**
 * Every GET route in the web middleware group that renders a page, read from
 * `php artisan route:list`. New pages are picked up without changing tests.
 */
export function pageRoutes(): AppRoute[] {
    const output = execFileSync(
        'php',
        ['artisan', 'route:list', '--json', '--method=GET'],
        { encoding: 'utf8' },
    );
    const routes = JSON.parse(output) as ArtisanRoute[];

    return routes
        .filter((route) => route.middleware.includes('web'))
        .filter(
            (route) =>
                !excludedPrefixes.some((prefix) =>
                    route.uri.startsWith(prefix),
                ),
        )
        .filter((route) => !((route.name ?? route.uri) in excluded))
        .filter(
            (route) =>
                !excludedJsonRoutes.some((pattern) =>
                    pattern.test(route.name ?? ''),
                ),
        )
        .map((route) => {
            const name = route.name ?? route.uri;

            return { name, path: fillParameters(name, route.uri) };
        });
}

function fillParameters(name: string, uri: string): string {
    const path = `/${uri}`.replace(/^\/\/$/, '/');
    const placeholders = [...path.matchAll(/\{(\w+)\??\}/g)];

    if (placeholders.length === 0) {
        return path;
    }

    const parameters = routeParameters[name];
    if (parameters === undefined) {
        throw new Error(
            `Route "${name}" (${uri}) has parameters. Add example values to routeParameters in tests/e2e/support/routes.ts, or exclude it with a reason.`,
        );
    }

    return path.replace(/\{(\w+)\??\}/g, (_, key: string) => {
        const value = parameters[key];
        if (value === undefined) {
            throw new Error(`Route "${name}" needs a value for "${key}".`);
        }

        return encodeURIComponent(value);
    });
}
