<?php

namespace App\Data;

use App\Enums\HouseholdRole;

/**
 * A Member as the Members page lists it.
 */
readonly class MemberRow
{
    public function __construct(
        public int $id,
        public string $label,
        public string $name,
        public ?string $nickname,
        public bool $hasLogin,
        public ?HouseholdRole $role,
        public bool $isLastOwner,
        public bool $holdsSubscriptions,
    ) {
        //
    }

    /**
     * Whether the Member cannot be removed right now, and so the button is off.
     */
    public function cannotBeRemoved(): bool
    {
        return $this->isLastOwner || $this->holdsSubscriptions;
    }
}
