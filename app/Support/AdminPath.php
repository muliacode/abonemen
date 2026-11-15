<?php

namespace App\Support;

use Illuminate\Routing\Router;
use InvalidArgumentException;

class AdminPath
{
    /**
     * Refuse a path for the Instance Admin section that would swallow a page the application already has.
     *
     * Only the first segment counts: "settings/team" would sit beside "settings/profile" and shadow nothing,
     * but an installer who wrote it is confused about what is taken, and a clear failure beats a puzzle.
     *
     * @throws InvalidArgumentException
     */
    public static function assertFree(string $path, Router $router): void
    {
        $first = explode('/', trim($path, '/'))[0];

        if ($first === '') {
            throw new InvalidArgumentException('ABONEMEN_ADMIN_PATH cannot be empty: that is the home page.');
        }

        foreach ($router->getRoutes()->getRoutes() as $route) {
            $taken = explode('/', trim($route->uri(), '/'))[0];

            if (strcasecmp($taken, $first) === 0 && ! str_starts_with($route->getName() ?? '', 'instance.')) {
                throw new InvalidArgumentException("ABONEMEN_ADMIN_PATH [{$path}] collides with the existing page [/{$route->uri()}]. Choose another path.");
            }
        }
    }
}
