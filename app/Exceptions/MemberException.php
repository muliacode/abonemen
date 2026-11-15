<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a change to a Household's Members would break one of the rules that keep a Household usable.
 */
final class MemberException extends RuntimeException
{
    public const LABEL_TAKEN = 'label_taken';

    public const LAST_OWNER = 'last_owner';

    public const LAST_MEMBER = 'last_member';

    public const HOLDS_SUBSCRIPTIONS = 'holds_subscriptions';

    public const NO_LOGIN = 'no_login';

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

    /**
     * The Household would be left without an Owner who can sign in.
     */
    public static function lastOwner(): self
    {
        return new self('A Household keeps at least one Owner linked to a User.', self::LAST_OWNER);
    }

    /**
     * The Household would be left without a Member.
     */
    public static function lastMember(): self
    {
        return new self('A Household keeps at least one Member.', self::LAST_MEMBER);
    }

    /**
     * The Member still holds Subscriptions, and each must have a Holder.
     */
    public static function holdsSubscriptions(): self
    {
        return new self('A Member that holds Subscriptions cannot be removed until they are reassigned.', self::HOLDS_SUBSCRIPTIONS);
    }

    /**
     * A role belongs to a User, and this Member has none.
     */
    public static function noLogin(): self
    {
        return new self('A Member without a login has no role.', self::NO_LOGIN);
    }
}
