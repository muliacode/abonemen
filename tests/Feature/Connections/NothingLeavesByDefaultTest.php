<?php

namespace Tests\Feature\Connections;

use App\Enums\Connection;
use App\Models\Household;
use App\Models\User;
use App\Support\Connections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RendersPages;
use Tests\Fixtures\Mail\HouseholdNoteMail;
use Tests\TestCase;

/**
 * The privacy promise: on a new instance nothing leaves the server unless someone switched it on, and no page asks
 * the browser to load anything from anywhere else. A request nobody planned for fails the test that made it.
 */
class NothingLeavesByDefaultTest extends TestCase
{
    use RefreshDatabase, RendersPages;

    public function test_an_outbound_request_nobody_faked_fails_instead_of_leaving_the_server(): void
    {
        $this->expectException(StrayRequestException::class);

        Http::get('https://tracker.invalid/collect');
    }

    /**
     * Everything a page makes the browser fetch on its own, and so tell another site about the visit. Links a person
     * chooses to click are not in this list.
     */
    #[DataProvider('pages')]
    public function test_no_page_loads_anything_from_another_origin(string $page): void
    {
        $screen = $this->render($page);

        $urls = [];

        foreach ($screen->query('//script[@src]|//link[@href]|//img[@src]|//iframe[@src]|//source[@src]|//video[@src]|//audio[@src]|//embed[@src]|//form[@action]') as $element) {
            $urls[] = $element->getAttribute($element->hasAttribute('src') ? 'src' : ($element->hasAttribute('href') ? 'href' : 'action'));
        }

        foreach ($screen->query('//img[@srcset]|//source[@srcset]') as $element) {
            foreach (explode(',', $element->getAttribute('srcset')) as $candidate) {
                $urls[] = trim(explode(' ', trim($candidate))[0]);
            }
        }

        foreach ($screen->query('//style') as $style) {
            preg_match_all('~(?:@import\s+(?:url\()?|url\()\s*[\'"]?([^\'")\s]+)~i', $style->textContent, $found);
            array_push($urls, ...$found[1]);
        }

        $elsewhere = array_values(array_filter($urls, fn (string $url) => $this->isAnotherOrigin($url)));

        $this->assertSame([], $elsewhere, "The page \"{$page}\" makes the browser contact another site.");
    }

    public function test_the_stylesheets_and_scripts_of_the_app_import_nothing_from_another_origin(): void
    {
        $sources = [...glob(resource_path('css/*.css')), ...glob(resource_path('js/*.js'))];

        $this->assertNotSame([], $sources, 'The scan should find the app\'s own css and js.');

        foreach ($sources as $file) {
            $this->assertDoesNotMatchRegularExpression('~(?:@import\s+(?:url\()?|url\()\s*[\'"]?(?:https?:)?//~i', file_get_contents($file), basename($file).' imports from another origin.');
        }
    }

    public function test_a_new_instance_sends_no_household_email_even_with_mail_set_up(): void
    {
        $this->mailDeliversWithoutSending();
        $household = Household::factory()->create();

        $this->sendNoteTo($household);
        $this->assertSame(0, $this->delivered());

        // The Instance Admin alone is not enough: the Household's Owner has to say yes as well.
        app(Connections::class)->makeAvailable(Connection::Email);

        $this->sendNoteTo($household);
        $this->assertSame(0, $this->delivered());

        app(Connections::class)->confirm(Connection::Email, $household, User::factory()->create());

        $this->sendNoteTo($household);
        $this->assertSame(1, $this->delivered());
    }

    private function isAnotherOrigin(string $url): bool
    {
        if (! preg_match('~^(?:https?:)?//~i', $url)) {
            return false;
        }

        $host = parse_url(str_starts_with($url, '//') ? "https:{$url}" : $url, PHP_URL_HOST);

        // This machine is not another site: the app itself and, while developing, the Vite dev server on loopback.
        return ! in_array($host, [parse_url((string) config('app.url'), PHP_URL_HOST), 'localhost', '127.0.0.1', '[::1]'], true);
    }

    private function mailDeliversWithoutSending(): void
    {
        // The app sees an SMTP server that delivers; the transport only keeps what it is given, so the test can count it.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp' => ['transport' => 'array', 'host' => 'smtp.home.example']]);
        Mail::forgetMailers();
    }

    private function sendNoteTo(Household $household): void
    {
        Mail::to('ada@example.com')->send(new HouseholdNoteMail($household));
    }

    private function delivered(): int
    {
        return Mail::mailer()->getSymfonyTransport()->messages()->count();
    }
}
