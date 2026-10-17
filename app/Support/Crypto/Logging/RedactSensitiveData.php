<?php

namespace App\Support\Crypto\Logging;

use App\Support\Crypto\DecryptedValues;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Log\Logger;
use JsonSerializable;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;
use Stringable;
use Throwable;

/**
 * A tap for every log channel (config/logging.php) that keeps plaintext out of
 * the logs (SEC-11):
 *
 * - values under sensitive keys in the context and extra data are replaced,
 *   at any depth;
 * - every value decrypted during this request or job is scrubbed from the
 *   message, the context and exceptions, whatever key it sits under;
 * - exceptions are logged as scrubbed text, including their previous
 *   exceptions, instead of objects the formatter would print as they are.
 */
final class RedactSensitiveData
{
    public const REDACTED = '[redacted]';

    /** Matched against the whole key, case-insensitively, after removing - and _. */
    private const SENSITIVE_KEYS = '/^(password|passwordconfirmation|currentpassword|token|accesstoken|refreshtoken|secret|clientsecret|apikey|email|name|displayname|price|amount|url|notes?|endpoint|twofactorsecret|twofactorrecoverycodes|recoverycodes?|masterkey|recoverykey)$/i';

    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof Monolog) {
            $monolog->pushProcessor(fn (LogRecord $record): LogRecord => $this->redact($record));
        }
    }

    public function redact(LogRecord $record): LogRecord
    {
        $values = app(DecryptedValues::class);

        return $record->with(
            message: $values->scrub($record->message),
            context: $this->clean($record->context, $values),
            extra: $this->clean($record->extra, $values),
        );
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function clean(array $data, DecryptedValues $values): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEYS, str_replace(['_', '-'], '', $key)) === 1 && ! $value instanceof Throwable) {
                $data[$key] = self::REDACTED;

                continue;
            }

            $data[$key] = match (true) {
                is_array($value) => $this->clean($value, $values),
                is_string($value) => $values->scrub($value),
                $value instanceof Throwable => $values->scrub($this->describe($value)),
                is_object($value) => $this->cleanObject($value, $values),
                default => $value,
            };
        }

        return $data;
    }

    /**
     * The formatter would print an object as it is, so it is turned into data
     * that can be cleaned first; an object that offers no such view is logged
     * by its class name only. Models are Arrayable, which already hides their
     * encrypted fields.
     *
     * @return array<mixed>|string
     */
    private function cleanObject(object $value, DecryptedValues $values): array|string
    {
        $data = match (true) {
            $value instanceof Arrayable => $value->toArray(),
            $value instanceof JsonSerializable => $value->jsonSerialize(),
            $value instanceof Stringable => (string) $value,
            default => '['.$value::class.']',
        };

        return match (true) {
            is_array($data) => $this->clean($data, $values),
            is_string($data) => $values->scrub($data),
            is_scalar($data) || $data === null => $values->scrub(var_export($data, true)),
            default => '['.$value::class.']',
        };
    }

    private function describe(Throwable $e): string
    {
        $text = '';

        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            $text .= ($text === '' ? '' : "\n\nPrevious: ").$current::class.': '.$current->getMessage()
                ." in {$current->getFile()}:{$current->getLine()}\n".$current->getTraceAsString();
        }

        return $text;
    }
}
