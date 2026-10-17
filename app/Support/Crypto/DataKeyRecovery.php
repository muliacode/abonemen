<?php

namespace App\Support\Crypto;

use App\Models\DataKey;

/**
 * Unseals every data key with the private half of the recovery key alone, for
 * when the master key is lost. Rewrapping them under a new master key is the
 * recovery command's job (#140).
 */
final class DataKeyRecovery
{
    /**
     * All or nothing: one key that cannot be unsealed stops the recovery, so a
     * new master key is never set up with a partial set of keys.
     *
     * @return array<string, DataKeyMaterial> By data key id.
     *
     * @throws KeyUnwrapFailed Naming the first key that could not be unsealed.
     */
    public function unsealAll(RecoverySecret $secret): array
    {
        $keys = [];

        foreach (DataKey::query()->orderBy('id')->cursor() as $row) {
            if ($row->wrapped_rk === null) {
                throw new KeyUnwrapFailed("Data key {$row->id} was never sealed to the recovery key.");
            }

            try {
                $bytes = KeySeal::unseal(
                    $secret,
                    $row->wrapped_rk,
                    KeyWrap::associatedData($row->id, $row->owner_type, $row->owner_id, $row->version),
                );
            } catch (KeyUnwrapFailed $e) {
                throw new KeyUnwrapFailed("Data key {$row->id}: {$e->getMessage()}", previous: $e);
            }

            $keys[$row->id] = new DataKeyMaterial($bytes, $row->version);
        }

        return $keys;
    }
}
