<?php

namespace App\Console\Concerns;

/**
 * Saves one setting in the .env file, for commands that generate a secret.
 */
trait WritesEnvironmentSetting
{
    /**
     * Save the value under the setting's name.
     *
     * Returns null when it was saved, otherwise the reason it was not: a missing file, or a value
     * that is already set and may not be replaced.
     */
    private function saveSetting(string $setting, string $value, bool $replace): ?string
    {
        $path = $this->laravel->environmentFilePath();

        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            return 'missing';
        }

        if (! $replace && preg_match('/^'.$setting.'=(.+)$/m', $contents, $matches) === 1 && trim($matches[1]) !== '') {
            return 'set';
        }

        $line = $setting.'='.$value;

        $contents = preg_match('/^'.$setting.'=.*$/m', $contents)
            ? preg_replace_callback('/^'.$setting.'=.*$/m', fn () => $line, $contents)
            : rtrim($contents, "\n")."\n{$line}\n";

        file_put_contents($path, $contents);

        return null;
    }
}
