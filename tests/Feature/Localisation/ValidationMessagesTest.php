<?php

namespace Tests\Feature\Localisation;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('fieldNames')]
    public function test_validation_messages_name_our_fields_in_the_users_language(string $locale, string $mainCurrency, string $timeZone): void
    {
        app()->setLocale($locale);

        $errors = Livewire::actingAs(User::factory()->create())
            ->test('create-household-modal')
            ->set('name', 'Home')
            ->set('mainCurrency', '')
            ->set('timezone', '')
            ->call('createHousehold')
            ->errors();

        $this->assertStringContainsStringIgnoringCase($mainCurrency, $errors->first('mainCurrency'));
        $this->assertStringContainsStringIgnoringCase($timeZone, $errors->first('timezone'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function fieldNames(): array
    {
        return [
            'Dutch' => ['nl', 'hoofdvaluta', 'tijdzone'],
            'Indonesian' => ['id', 'mata uang utama', 'zona waktu'],
            'French' => ['fr', 'devise principale', 'fuseau horaire'],
            'German' => ['de', 'Hauptwährung', 'Zeitzone'],
        ];
    }

    #[DataProvider('currencyNames')]
    public function test_currencies_are_named_in_the_users_language(string $locale, string $usDollar): void
    {
        app()->setLocale($locale);

        $user = User::factory()->create();
        $household = Household::factory()->withUser($user)->create();

        Livewire::actingAs($user)->test('create-household-modal')->assertSee($usDollar);
        Livewire::actingAs($user)->test('pages::households.edit', ['household' => $household])->assertSee($usDollar);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function currencyNames(): array
    {
        return [
            'Dutch' => ['nl', 'Amerikaanse dollar'],
            'Indonesian' => ['id', 'Dolar Amerika Serikat'],
            'French' => ['fr', 'dollar des États-Unis'],
            'German' => ['de', 'US-Dollar'],
            'English' => ['en', 'US Dollar'],
        ];
    }

    #[DataProvider('languageFieldNames')]
    public function test_the_language_field_has_a_name_in_every_language(string $locale, string $name): void
    {
        app()->setLocale($locale);

        $errors = Livewire::actingAs(User::factory()->create())
            ->test('pages::settings.language')
            ->set('locale', '')
            ->call('updateLanguage')
            ->errors();

        $this->assertStringContainsStringIgnoringCase($name, $errors->first('locale'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function languageFieldNames(): array
    {
        return [
            'Dutch' => ['nl', 'taal'],
            'Indonesian' => ['id', 'bahasa'],
            'French' => ['fr', 'langue'],
            'German' => ['de', 'Sprache'],
        ];
    }
}
