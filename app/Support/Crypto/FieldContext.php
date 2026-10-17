<?php

namespace App\Support\Crypto;

/**
 * Where an encrypted household field lives: its key is derived from the
 * household's key at that version, table and column; its associated data is
 * the row and the household.
 */
final readonly class FieldContext
{
    public function __construct(
        public string $householdId,
        public int $keyVersion,
        public string $rowId,
        public string $table,
        public string $column,
    ) {}
}
