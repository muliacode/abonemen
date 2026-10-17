<?php

namespace App\Support\Crypto;

use App\Models\DataKey;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Creates data keys and unwraps them with the master key.
 *
 * Unwrapped keys are kept in memory for the rest of the request or job, never
 * stored. The ring is a singleton; forget() drops what it holds.
 */
final class DataKeyRing
{
    /** @var array<string, DataKeyMaterial> */
    private array $unwrapped = [];

    public function __construct(private readonly MasterKeyLoader $masterKey) {}

    /**
     * Creates the global data key. There is one per instance.
     */
    public function createGlobal(): DataKeyMaterial
    {
        return DB::transaction(function (): DataKeyMaterial {
            if ($this->hasGlobal()) {
                throw new LogicException('The global data key already exists.');
            }

            return $this->store(DataKey::OWNER_GLOBAL, DataKey::INSTANCE, 1);
        });
    }

    public function hasGlobal(): bool
    {
        return $this->latest(DataKey::OWNER_GLOBAL, DataKey::INSTANCE) !== null;
    }

    /**
     * Creates the first household data key, or the next version of it.
     */
    public function createHousehold(string $householdId): DataKeyMaterial
    {
        return DB::transaction(function () use ($householdId): DataKeyMaterial {
            $version = ($this->latest(DataKey::OWNER_HOUSEHOLD, $householdId)->version ?? 0) + 1;

            return $this->store(DataKey::OWNER_HOUSEHOLD, $householdId, $version);
        });
    }

    /**
     * @throws DataKeyMissing
     * @throws KeyUnwrapFailed
     */
    public function global(): DataKeyMaterial
    {
        return $this->unwrap(DataKey::OWNER_GLOBAL, DataKey::INSTANCE, null);
    }

    /**
     * The household's newest active key, or the given version of it.
     *
     * @throws DataKeyMissing
     * @throws KeyUnwrapFailed
     */
    public function household(string $householdId, ?int $version = null): DataKeyMaterial
    {
        return $this->unwrap(DataKey::OWNER_HOUSEHOLD, $householdId, $version);
    }

    public function forget(): void
    {
        $this->unwrapped = [];
    }

    private function unwrap(string $ownerType, string $ownerId, ?int $version): DataKeyMaterial
    {
        $cacheKey = "{$ownerType}\0{$ownerId}\0".($version ?? 'latest');

        if (isset($this->unwrapped[$cacheKey])) {
            return $this->unwrapped[$cacheKey];
        }

        $row = $version === null
            ? $this->latest($ownerType, $ownerId)
            : DataKey::query()->where(['owner_type' => $ownerType, 'owner_id' => $ownerId, 'version' => $version])->first();

        if ($row === null) {
            throw new DataKeyMissing("No {$ownerType} data key for {$ownerId}".($version === null ? '.' : " at version {$version}."));
        }

        $bytes = KeyWrap::unwrap(
            $this->masterKey->key(),
            $row->wrapped_mk,
            KeyWrap::associatedData($row->id, $row->owner_type, $row->owner_id, $row->version),
        );

        return $this->unwrapped[$cacheKey] = new DataKeyMaterial($bytes, $row->version);
    }

    private function latest(string $ownerType, string $ownerId): ?DataKey
    {
        return DataKey::query()
            ->where(['owner_type' => $ownerType, 'owner_id' => $ownerId, 'status' => DataKey::STATUS_ACTIVE])
            ->orderByDesc('version')
            ->first();
    }

    private function store(string $ownerType, string $ownerId, int $version): DataKeyMaterial
    {
        $key = new DataKeyMaterial(random_bytes(DataKeyMaterial::BYTES), $version);
        $row = new DataKey(['owner_type' => $ownerType, 'owner_id' => $ownerId, 'version' => $version, 'status' => DataKey::STATUS_ACTIVE]);
        $row->id = $row->newUniqueId();
        $row->wrapped_mk = KeyWrap::wrap(
            $this->masterKey->key(),
            $key->bytes(),
            KeyWrap::associatedData($row->id, $ownerType, $ownerId, $version),
        );
        $row->save();

        return $this->unwrapped["{$ownerType}\0{$ownerId}\0{$version}"] = $key;
    }
}
