<?php

namespace App\Data;

readonly class UserHousehold
{
    public function __construct(
        public int $id,
        public string $ulid,
        public string $name,
        public ?string $role,
        public ?string $roleLabel,
        public bool $canManage = false,
        public ?bool $isCurrent = null,
    ) {
        //
    }
}
