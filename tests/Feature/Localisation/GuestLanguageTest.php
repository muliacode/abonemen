<?php

namespace Tests\Feature\Localisation;

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GuestLanguageTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('browserLanguages')]
    public function test_a_guest_sees_the_language_their_browser_asks_for(string $acceptLanguage, string $expectedWord, string $expectedHtmlLang): void
    {
        $this->withHeader('Accept-Language', $acceptLanguage)
            ->get(route('login'))
            ->assertOk()
            ->assertSee($expectedWord)
            ->assertSee('lang="'.$expectedHtmlLang.'"', false);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function browserLanguages(): array
    {
        return [
            'Dutch' => ['nl', 'Wachtwoord', 'nl'],
            'German with a region' => ['de-DE,de;q=0.9,en;q=0.8', 'Passwort', 'de'],
            'French' => ['fr-CH, fr;q=0.9', 'Mot de passe', 'fr'],
            'Indonesian' => ['id-ID,id;q=0.9', 'Kata Sandi', 'id'],
            'first supported language wins over an unsupported one' => ['ja,fr;q=0.8,en;q=0.5', 'Mot de passe', 'fr'],
            'English' => ['en-GB,en;q=0.9', 'Password', 'en'],
            'nothing supported' => ['ja,zh;q=0.9', 'Password', 'en'],
            'no preference' => ['', 'Password', 'en'],
        ];
    }

    public function test_a_signed_in_users_own_language_wins_over_the_browser(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'nl')
            ->get(route('households.index'))
            ->assertSee('Foyers')
            ->assertSee('lang="fr"', false);
    }

    public function test_a_signed_in_user_without_a_chosen_language_gets_the_one_their_browser_asks_for(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'nl')
            ->get(route('households.index'))
            ->assertSee('Huishoudens');
    }

    public function test_a_new_account_starts_in_the_language_it_was_registered_in(): void
    {
        $this->withHeader('Accept-Language', 'de')
            ->post(route('register.store'), [
                'name' => 'Sanne',
                'email' => 'sanne@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->assertSessionHasNoErrors();

        $this->assertSame(Locale::German, User::whereEmail('sanne@example.com')->sole()->locale);
    }

    public function test_a_new_account_registered_in_an_unsupported_language_gets_the_default(): void
    {
        $this->withHeader('Accept-Language', 'ja')
            ->post(route('register.store'), [
                'name' => 'Sanne',
                'email' => 'sanne@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->assertSessionHasNoErrors();

        $this->assertSame(Locale::English, User::whereEmail('sanne@example.com')->sole()->locale);
    }
}
