<?php

namespace Tests\Feature\Instance;

use App\Support\AdminPath;
use Illuminate\Routing\Router;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The Instance Admin section lives at a human-readable path that is not the one everyone tries, and that an
 * installer can change. A path that would swallow a page the application already has is refused at startup.
 */
class AdminPathTest extends TestCase
{
    public function test_the_default_path_is_instance(): void
    {
        $this->assertSame('instance', config('abonemen.admin_path'));
        $this->assertSame('/instance', route('instance.households', absolute: false));
    }

    public function test_the_default_is_not_a_path_everybody_tries(): void
    {
        $this->assertNotContains(config('abonemen.admin_path'), ['admin', 'administrator', 'dashboard', 'wp-admin', 'backend', 'manage', 'panel']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function collisions(): array
    {
        return [
            'a page of the app' => ['login'],
            'settings' => ['settings'],
            'the first-run page' => ['setup'],
            'registration' => ['register'],
            'a deeper path that starts on one' => ['settings/team'],
            'the home page' => [''],
        ];
    }

    #[DataProvider('collisions')]
    public function test_a_path_that_collides_with_an_existing_page_is_refused(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);

        AdminPath::assertFree($path, app(Router::class));
    }

    public function test_a_free_path_is_accepted(): void
    {
        AdminPath::assertFree('back-office', app(Router::class));

        $this->assertTrue(true);
    }
}
