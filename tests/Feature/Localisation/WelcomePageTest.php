<?php

namespace Tests\Feature\Localisation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('welcomeLines')]
    public function test_the_welcome_page_is_shown_in_the_visitors_language(string $acceptLanguage, string $tagline, string $logIn): void
    {
        $this->withHeader('Accept-Language', $acceptLanguage)
            ->get('/')
            ->assertOk()
            ->assertSee('Abonemen')
            ->assertSee($tagline)
            ->assertSee($logIn);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function welcomeLines(): array
    {
        return [
            'English' => ['en', 'Know exactly what you pay for, when, and why, without giving up your privacy.', 'Log in'],
            'Dutch' => ['nl', 'Weet precies waarvoor je betaalt, wanneer en waarom, zonder je privacy op te geven.', 'Inloggen'],
            'Indonesian' => ['id', 'Ketahui dengan pasti apa yang Anda bayar, kapan, dan mengapa, tanpa mengorbankan privasi Anda.', 'Masuk'],
            'French' => ['fr', 'Sachez exactement ce que vous payez, quand et pourquoi, sans renoncer à votre vie privée.', 'Se connecter'],
            'German' => ['de', 'Wissen Sie genau, wofür Sie wann und warum bezahlen, ohne Ihre Privatsphäre aufzugeben.', 'Anmelden'],
        ];
    }

    public function test_a_signed_in_visitor_is_offered_their_dashboard_instead_of_signing_in(): void
    {
        $user = User::factory()->withHousehold()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee($user->homeUrl())
            ->assertDontSee(route('login'));
    }

    public function test_no_stock_laravel_text_is_left_on_the_welcome_page(): void
    {
        $this->get('/')
            ->assertDontSee('Laracasts')
            ->assertDontSee('Deploy now')
            ->assertDontSee('rich ecosystem');
    }
}
