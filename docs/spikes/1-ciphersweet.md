# Spike 1: CipherSweet multi-tenant encryption with per-household keys

Issue: [#12](https://github.com/muliacode/abonemen/issues/12). Requirements: SEC-01, SEC-03, NFR-02. Decision: ADR 3.

**Outcome: ADR 3 is accepted, with three changes.** CipherSweet's multi-tenant mode does what the design needs, but its stock backend is too slow for NFR-02 by itself, and two framework details need care. The proof of concept is on the [`spike/12-ciphersweet`](https://github.com/muliacode/abonemen/tree/spike/12-ciphersweet) branch and is not merged; the Crypto issues (#18 to #24) build the real thing.

## What was built

| Part | How |
| --- | --- |
| Household fields | An `Encrypted` Eloquent cast on CipherSweet's multi-tenant mode. The tenant is the row's `household_id`, its HDK is the tenant root key, CipherSweet derives a key per table and column, and the associated data is the row's ULID plus the household id |
| Account fields | `spatie/laravel-ciphersweet` with a custom key-provider factory that supplies the GDK. User email and name are encrypted, and the email has a blind index truncated to 32 bits (lowercased) |
| TOTP | Fortify's secret and recovery codes are encrypted under a key derived from the GDK through `Fortify::encryptUsing()`, not under `APP_KEY` |
| Keys | Stand-ins only: the GDK comes from an environment variable and household keys from an in-memory ring. The key hierarchy is #19 |

## Results

### Done when

- [x] **500 subscriptions decrypt in under 100 ms**, with all five encrypted fields read: 28 ms median, 29 ms p95 with the faster backend described below, or 30 ms median and 42 ms p95 on a single core. With CipherSweet's stock `BoringCrypto` backend it was 90 ms median and **107 ms p95, over budget**.
- [x] **Ciphertext copied to another row fails to decrypt**: copied to another row of the same household, moved to another household, read with the wrong column's key, or read after the household key is deleted. All four fail. The same holds for a user's email copied to another user.
- [x] **Outcome written; ADR 3 accepted**, with the changes below.

### Benchmark

`php artisan spike:decrypt-benchmark` on the PoC branch: 500 subscriptions in a SQLite file in WAL mode, 30 runs after a warm-up, PHP 8.3 with OPcache on and no coverage driver, on a 4-vCPU Intel Xeon at 2.1 GHz (cloud VM, comparable to a mid-range VPS).

| Measurement | Stock BoringCrypto, median / p95 | FastBoringCrypto, median / p95 |
| --- | --- | --- |
| Query only, no decryption | 10 / 12 ms | 7 / 11 ms |
| Query and decrypt all 5 fields | 90 / 107 ms | **28 / 29 ms** |
| Query and decrypt the name only | 20 / 21 ms | 12 / 16 ms |
| Cold: derive field keys first | 80 / 101 ms | 28 / 30 ms |

Two things to know when reading these numbers:

- **A coverage driver doubles the cost.** With PCOV loaded, the stock backend took 215 ms. Never benchmark with Xdebug or PCOV on.
- **Decryption is lazy.** The cast decrypts a field only when it is read, so a list that shows names and prices pays for those columns only.

## Changes to the design

1. **Use a faster backend with the same wire format.** Raw libsodium decrypts 2,500 fields in about 1.5 ms; CipherSweet's `BoringCrypto` takes about 90 ms. The cost is PHP overhead: a pure-PHP constant-time base64 decoder and two BLAKE2b subkey derivations on every call. `FastBoringCrypto` extends `BoringCrypto` and keeps its algorithm and format (XChaCha20 with a BLAKE2b MAC, `brng:` header), but uses libsodium's own constant-time base64 and caches the subkeys per field key. It is about 20 times faster, and ciphertexts move freely between the two in both directions, which a test checks. Because the format is unchanged, the app can drop back to the stock backend at any time.
2. **The cast must return the ULID and household instead of setting them.** Encrypting needs the row's ULID before insert, but `HasUlids` assigns it only on `creating`. Setting the key on the model from inside a cast's `set()` is silently lost, because Eloquent merges the cast's result into a copy of the attributes taken before `set()` runs. The fix is to return them: `set()` returns the ciphertext together with a generated ULID and the bound household when either is missing. When no household is bound and none is set, the cast refuses to encrypt.
3. **Account fields need ULID keys and a wrapper trait.**
   - **ULID keys.** Spatie encrypts on `saving`, which also comes before `creating`, so the wrapper trait assigns the ULID in its own `saving` listener first. The `users` table moves to ULID keys, and spatie's `blind_indexes` migration is replaced with one using `ulidMorphs`.
   - **Wrapper trait.** Spatie makes the model implement `configureCipherSweet(EncryptedRow)`, which would put CipherSweet in `App\Models` and break the architecture rule that only `App\Support\Crypto` touches cryptography. A small `EncryptsAccountFields` trait in `App\Support\Crypto` wraps it, and models only list their fields.

## Findings for the Crypto issues

- **#19, data keys.** The household key provider loads tenants on demand from a key ring instead of being built with every household up front, which CipherSweet's `MultiTenantProvider` expects. The ring is the place to unwrap versioned HDKs. Rotation (#142) must flush cached field keys; `HouseholdCipher::flush()` exists for that.
- **#21, the cast.** Pass the household id and ULID as associated data literals. Store money as a string in the encrypted column, as the data model says. The `Encrypted` cast only handles strings; booleans, integers and JSON need typed variants or a cast parameter.
- **#22, account fields.** Use `addOptionalTextField` with the ULID as the associated-data source. Blind indexes use the `Lowercase` transformation and 32 bits. `whereBlind()` works on ULID morphs. Set spatie's `permit_empty` to true: with false, a query that selects only some columns leaves the whole row encrypted, because spatie swallows the exception and skips decrypting.
- **#34, two-factor sign-in.** `Fortify::encryptUsing()` with a GDK-derived encrypter is enough; no override of Fortify's storage is needed. Those values are not bound to the row, unlike other account fields. That is acceptable because they are useless without the matching account.
- **#9, the leak test.** On the spike branch the leak test failed as soon as user fields were encrypted, because `users.name` and `users.email` were still on its plaintext list. That is the intended ratchet: #22 removes them. With subscription canaries added it passes, and dropping the cast from one field makes it fail with that field's name.

## Not covered

The real key hierarchy, key rotation, the MySQL and PostgreSQL drivers, and the cost of encrypting on write. Writing 500 subscriptions in one transaction was not a concern in the spike.
