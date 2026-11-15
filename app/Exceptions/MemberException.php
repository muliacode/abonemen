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

    public const NO_USER = 'no_user';

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
     * A role belongs to a User, and this Member is not linked to one.
     */
    public static function noUser(): self
    {
        return new self('A Member that is not linked to a User has no role.', self::NO_USER);
    }

    /**
     * Say what went wrong, for the person who asked for it.
     *
     * A taken label has two ways out: give the Member a nickname, or, when one was already given, choose another.
     */
    public function userMessage(bool $hadNickname = false): string
    {
        return match ($this->reason) {
            self::LABEL_TAKEN => $hadNickname
                ? __('Another Member here is already called :label. Choose a different nickname.', ['label' => $this->label])
                : __('Another Member here is already called :label. Give this Member a nickname to tell them apart.', ['label' => $this->label]),
            self::LAST_OWNER => __('The Household needs an Owner who can sign in. Make someone else an Owner first.'),
            self::LAST_MEMBER => __('The Household needs at least one Member.'),
            self::HOLDS_SUBSCRIPTIONS => __('This Member holds Subscriptions. Reassign them first.'),
            default => __('That could not be saved.'),
        };
    }
}
