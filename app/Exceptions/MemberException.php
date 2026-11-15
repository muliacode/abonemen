<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a change to a Household's Members would break one of the rules that keep a Household usable.
 */
final class MemberException extends RuntimeException
{
    public const LABEL_TAKEN = 'label_taken';

    private function __construct(string $message, public readonly string $reason, public readonly ?string $label = null)
    {
        parent::__construct($message);
    }

    /**
     * Another Member in the Household already goes by this label.
     *
     * The label is the clashing Member's, as it is shown, so a screen can name it.
     */
    public static function labelTaken(string $label): self
    {
        return new self("Another Member in this Household is already called \"{$label}\".", self::LABEL_TAKEN, $label);
    }
}
