<?php

namespace Tests\Arch\Support;

/**
 * The domains of docs/product/technical-design.md and the model each one owns.
 *
 * Models stay flat in app/Models, so ownership cannot be read from a namespace.
 * Add every new model here; ArchitectureTest fails for a model without an owner.
 */
final class Domains
{
    public const ALL = [
        'Accounts',
        'Crypto',
        'Data',
        'Insights',
        'Money',
        'Platform',
        'Reminders',
        'Subscriptions',
    ];

    /**
     * Model class basename => owning domain.
     */
    public const MODEL_OWNERS = [
        'DataKey' => 'Crypto',
        'User' => 'Accounts',
    ];

    /**
     * Namespaces whose code belongs to a domain, one per layer.
     *
     * @return list<string>
     */
    public static function namespaces(string $domain): array
    {
        return [
            "App\\Actions\\{$domain}",
            "App\\Events\\{$domain}",
            "App\\Http\\Controllers\\{$domain}",
            "App\\Http\\Requests\\{$domain}",
            "App\\Jobs\\{$domain}",
        ];
    }

    /**
     * Fully qualified models that a domain does not own.
     *
     * @return list<class-string>
     */
    public static function foreignModels(string $domain): array
    {
        $foreign = [];

        foreach (self::MODEL_OWNERS as $model => $owner) {
            if ($owner !== $domain) {
                $foreign[] = "App\\Models\\{$model}";
            }
        }

        return $foreign;
    }

    /**
     * Fully qualified class names of every model in app/Models.
     *
     * @return list<class-string>
     */
    public static function modelClasses(): array
    {
        $files = glob(dirname(__DIR__, 3).'/app/Models/*.php') ?: [];

        return array_map(
            fn (string $file): string => 'App\\Models\\'.basename($file, '.php'),
            $files,
        );
    }
}
