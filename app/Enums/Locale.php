<?php

namespace App\Enums;

/**
 * The languages Abonemen is available in.
 */
enum Locale: string
{
    case English = 'en';
    case Dutch = 'nl';
    case Indonesian = 'id';
    case French = 'fr';
    case German = 'de';

    /**
     * Get the language's name in that language, so people can find their own.
     */
    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Dutch => 'Nederlands',
            self::Indonesian => 'Bahasa Indonesia',
            self::French => 'Français',
            self::German => 'Deutsch',
        };
    }

    /**
     * Get the locale codes of every language.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_map(fn (self $locale) => $locale->value, self::cases());
    }
}
