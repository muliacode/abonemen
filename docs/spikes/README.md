# Spikes

Short proofs of concept from the [technical design](../product/technical-design.md#spikes-before-building), each confirming or changing a decision before building starts. Every spike keeps its proof-of-concept code on its own `spike/` branch; that code is never merged. What is merged is the outcome below and any change to the design.

| Spike | Issue | Outcome |
| --- | --- | --- |
| [1. CipherSweet multi-tenant encryption](1-ciphersweet.md) | [#12](https://github.com/muliacode/abonemen/issues/12) | ADR 3 accepted with changes: a faster backend with the same wire format, ULIDs returned by the cast, ULID keys for users |
| [2. Passkeys with Fortify and Inertia v3](2-passkeys.md) | [#13](https://github.com/muliacode/abonemen/issues/13) | ADR 11 holds: Fortify integrates `laravel/passkeys`; a passkey as second factor is our own step |
| [4. Cron mode on shared hosting](4-shared-hosting.md) | [#15](https://github.com/muliacode/abonemen/issues/15) | Kit ready and dry run passed; the real-host run is still to do |
